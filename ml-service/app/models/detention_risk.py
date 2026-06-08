"""Feature engineering + XGBoost wrapper for the detention-risk classifier.

Per-student features (SCALABLE_ARCHITECTURE.md §8):
  - attendance_pct          current attendance % (trailing 30 days)
  - trend_7d / 14d / 30d    change in attendance % vs. the preceding window
                            of equal length (positive = improving)
  - dept_avg_pct            department-wide average attendance %, as of date
  - subject_variance        variance of the student's per-subject attendance %
  - longest_absence_streak  longest run of consecutive marked-Absent sessions
"""
from __future__ import annotations

from dataclasses import dataclass
from datetime import date

import joblib
import numpy as np
import pandas as pd
from xgboost import XGBClassifier

FEATURE_COLUMNS = [
    "attendance_pct",
    "trend_7d",
    "trend_14d",
    "trend_30d",
    "dept_avg_pct",
    "subject_variance",
    "longest_absence_streak",
]


def _pct_present(rows: pd.DataFrame) -> float:
    if rows.empty:
        return 0.0
    return float((rows["status"] == "Present").mean() * 100)


def _trend(history: pd.DataFrame, as_of: pd.Timestamp, window_days: int) -> float:
    """Attendance % in the last `window_days` minus the % in the equal-length
    window immediately preceding it. Positive => improving, negative => slipping.
    Returns 0.0 when either window has no marked sessions (nothing to compare).
    """
    recent_start = as_of - pd.Timedelta(days=window_days - 1)
    prior_end = recent_start - pd.Timedelta(days=1)
    prior_start = recent_start - pd.Timedelta(days=window_days)

    recent = history[history["attendance_date"].between(recent_start, as_of)]
    prior = history[history["attendance_date"].between(prior_start, prior_end)]

    if recent.empty or prior.empty:
        return 0.0

    return round(_pct_present(recent) - _pct_present(prior), 2)


def _longest_absence_streak(history: pd.DataFrame) -> int:
    """Longest run of consecutive *marked* sessions (in date order) where the
    student was Absent — a proxy for sustained disengagement vs. one-offs.
    """
    longest = current = 0
    for status in history.sort_values("attendance_date")["status"]:
        if status == "Absent":
            current += 1
            longest = max(longest, current)
        else:
            current = 0

    return longest


@dataclass
class FeatureBuilder:
    """Builds the feature row(s) for the detention-risk model from raw
    attendance history (see `app.data.loaders.load_attendance_history`).
    Only data on/before `as_of` is used, so the same builder produces
    leakage-free features for both training labels and live predictions.
    """

    attendance: pd.DataFrame  # student_id, subject_id, attendance_date, status, department

    def build_one(self, student_id: int, as_of: date) -> dict[str, float] | None:
        as_of_ts = pd.Timestamp(as_of)
        history = self.attendance[
            (self.attendance["student_id"] == student_id)
            & (self.attendance["attendance_date"] <= as_of_ts)
        ]

        if history.empty:
            return None

        department = history["department"].iloc[-1]
        trailing_30 = history[history["attendance_date"] >= as_of_ts - pd.Timedelta(days=29)]

        dept_history = self.attendance[
            (self.attendance["department"] == department)
            & (self.attendance["attendance_date"] <= as_of_ts)
        ]

        subject_pcts = history.groupby("subject_id")["status"].apply(
            lambda s: (s == "Present").mean() * 100
        )

        return {
            "attendance_pct": round(_pct_present(trailing_30), 2),
            "trend_7d": _trend(history, as_of_ts, 7),
            "trend_14d": _trend(history, as_of_ts, 14),
            "trend_30d": _trend(history, as_of_ts, 30),
            "dept_avg_pct": round(_pct_present(dept_history), 2),
            "subject_variance": round(float(subject_pcts.var(ddof=0)), 2) if len(subject_pcts) > 1 else 0.0,
            "longest_absence_streak": _longest_absence_streak(history),
        }

    def build_many(self, student_ids: list[int], as_of: date) -> pd.DataFrame:
        """One row per student that has attendance history on/before `as_of`."""
        rows = [
            {"student_id": sid, **features}
            for sid in student_ids
            if (features := self.build_one(sid, as_of)) is not None
        ]

        return pd.DataFrame(rows, columns=["student_id", *FEATURE_COLUMNS])


class DetentionRiskModel:
    """Thin wrapper around the trained XGBoost classifier — load once, reuse."""

    def __init__(self, model: XGBClassifier, version: str):
        self.model = model
        self.version = version

    @classmethod
    def load(cls, path: str, version: str) -> "DetentionRiskModel":
        return cls(model=joblib.load(path), version=version)

    def predict(self, features: pd.DataFrame) -> pd.DataFrame:
        """Returns one row per input student: student_id, risk_score (0-1),
        predicted_detention (bool), confidence (0.5-1.0).
        """
        if features.empty:
            return pd.DataFrame(columns=["student_id", "risk_score", "predicted_detention", "confidence"])

        probabilities = self.model.predict_proba(features[FEATURE_COLUMNS])[:, 1]

        # Confidence = distance from the 0.5 decision boundary, rescaled to
        # 0.5-1.0 (a coin-flip probability of 0.5 => 0.5 confidence; a
        # probability of 0.0 or 1.0 => full 1.0 confidence in the call).
        confidence = 0.5 + np.abs(probabilities - 0.5)

        return pd.DataFrame({
            "student_id": features["student_id"].to_numpy(),
            "risk_score": probabilities.round(3),
            "predicted_detention": probabilities >= 0.5,
            "confidence": confidence.round(3),
        })
