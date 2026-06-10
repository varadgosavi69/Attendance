"""Unit tests for FeatureBuilder and the private helper functions in
app.models.detention_risk.
"""
from datetime import date

import pandas as pd
import pytest

from app.models.detention_risk import (
    FEATURE_COLUMNS,
    FeatureBuilder,
    _longest_absence_streak,
    _pct_present,
    _trend,
)


def _df(rows: list[dict]) -> pd.DataFrame:
    df = pd.DataFrame(rows)
    if "attendance_date" in df.columns:
        df["attendance_date"] = pd.to_datetime(df["attendance_date"])
    return df


# ── _pct_present ─────────────────────────────────────────────────────────────


class TestPctPresent:
    def test_all_present(self):
        df = _df([{"status": "Present"}, {"status": "Present"}])
        assert _pct_present(df) == 100.0

    def test_all_absent(self):
        df = _df([{"status": "Absent"}, {"status": "Absent"}])
        assert _pct_present(df) == 0.0

    def test_mixed_50_50(self):
        df = _df([{"status": "Present"}, {"status": "Absent"}] * 5)
        assert _pct_present(df) == pytest.approx(50.0)

    def test_empty_returns_zero(self):
        assert _pct_present(pd.DataFrame(columns=["status"])) == 0.0


# ── _longest_absence_streak ───────────────────────────────────────────────────


class TestLongestAbsenceStreak:
    def test_no_absences_returns_zero(self):
        df = _df([
            {"attendance_date": "2026-01-01", "status": "Present"},
            {"attendance_date": "2026-01-02", "status": "Present"},
        ])
        assert _longest_absence_streak(df) == 0

    def test_single_absence(self):
        df = _df([
            {"attendance_date": "2026-01-01", "status": "Present"},
            {"attendance_date": "2026-01-02", "status": "Absent"},
            {"attendance_date": "2026-01-03", "status": "Present"},
        ])
        assert _longest_absence_streak(df) == 1

    def test_streak_of_three(self):
        df = _df([
            {"attendance_date": "2026-01-01", "status": "Absent"},
            {"attendance_date": "2026-01-02", "status": "Absent"},
            {"attendance_date": "2026-01-03", "status": "Absent"},
            {"attendance_date": "2026-01-04", "status": "Present"},
        ])
        assert _longest_absence_streak(df) == 3

    def test_multiple_streaks_returns_longest(self):
        df = _df([
            {"attendance_date": "2026-01-01", "status": "Absent"},
            {"attendance_date": "2026-01-02", "status": "Absent"},
            {"attendance_date": "2026-01-03", "status": "Present"},
            {"attendance_date": "2026-01-04", "status": "Absent"},
        ])
        assert _longest_absence_streak(df) == 2

    def test_all_absent(self):
        df = _df([
            {"attendance_date": f"2026-01-{d:02d}", "status": "Absent"}
            for d in range(1, 6)
        ])
        assert _longest_absence_streak(df) == 5


# ── _trend ────────────────────────────────────────────────────────────────────


class TestTrend:
    def _history(self, days_status: list[tuple[str, str]]) -> pd.DataFrame:
        return _df([{"attendance_date": d, "status": s} for d, s in days_status])

    def test_improving_trend(self):
        # prior 7 days (Jan 1-7): all Absent; recent 7 days (Jan 8-14): all Present
        rows = (
            [(f"2026-01-{d:02d}", "Absent") for d in range(1, 8)]
            + [(f"2026-01-{d:02d}", "Present") for d in range(8, 15)]
        )
        result = _trend(self._history(rows), pd.Timestamp("2026-01-14"), window_days=7)
        assert result == pytest.approx(100.0, abs=0.1)

    def test_declining_trend(self):
        # prior 7 days: all Present; recent 7 days: all Absent
        rows = (
            [(f"2026-01-{d:02d}", "Present") for d in range(1, 8)]
            + [(f"2026-01-{d:02d}", "Absent") for d in range(8, 15)]
        )
        result = _trend(self._history(rows), pd.Timestamp("2026-01-14"), window_days=7)
        assert result == pytest.approx(-100.0, abs=0.1)

    def test_no_recent_data_returns_zero(self):
        # Records only in Jan 1-7; as_of far in the future leaves recent window empty
        rows = [(f"2026-01-{d:02d}", "Present") for d in range(1, 8)]
        result = _trend(self._history(rows), pd.Timestamp("2026-01-30"), window_days=7)
        assert result == 0.0

    def test_no_prior_data_returns_zero(self):
        # Only one day of data; prior window is empty
        rows = [("2026-01-22", "Present")]
        result = _trend(self._history(rows), pd.Timestamp("2026-01-22"), window_days=7)
        assert result == 0.0


# ── FeatureBuilder ────────────────────────────────────────────────────────────


class TestFeatureBuilder:
    def _full_df(
        self,
        student_id: int,
        n_present: int,
        n_absent: int,
        department: str = "CSE",
        start: str = "2026-01-01",
    ) -> pd.DataFrame:
        base = pd.Timestamp(start)
        rows = []
        for i in range(n_present):
            rows.append({
                "student_id": student_id,
                "subject_id": (i % 4) + 1,
                "attendance_date": base + pd.Timedelta(days=i),
                "status": "Present",
                "department": department,
            })
        for i in range(n_absent):
            rows.append({
                "student_id": student_id,
                "subject_id": (i % 4) + 1,
                "attendance_date": base + pd.Timedelta(days=n_present + i),
                "status": "Absent",
                "department": department,
            })
        return pd.DataFrame(rows)

    def test_build_one_returns_none_for_unknown_student(self, sample_df):
        fb = FeatureBuilder(attendance=sample_df)
        assert fb.build_one(student_id=9999, as_of=date(2026, 3, 1)) is None

    def test_build_one_returns_all_seven_features(self, sample_df):
        fb = FeatureBuilder(attendance=sample_df)
        result = fb.build_one(student_id=1, as_of=date(2026, 3, 1))
        assert result is not None
        assert set(result.keys()) == set(FEATURE_COLUMNS)

    def test_build_one_attendance_pct_all_present(self):
        df = self._full_df(student_id=1, n_present=30, n_absent=0)
        fb = FeatureBuilder(attendance=df)
        result = fb.build_one(student_id=1, as_of=date(2026, 1, 30))
        assert result["attendance_pct"] == pytest.approx(100.0)

    def test_build_one_attendance_pct_all_absent(self):
        df = self._full_df(student_id=1, n_present=0, n_absent=30)
        fb = FeatureBuilder(attendance=df)
        result = fb.build_one(student_id=1, as_of=date(2026, 1, 30))
        assert result["attendance_pct"] == pytest.approx(0.0)

    def test_build_one_longest_absence_streak(self):
        rows = [
            {"student_id": 1, "subject_id": 1, "attendance_date": pd.Timestamp("2026-01-01"), "status": "Absent", "department": "CSE"},
            {"student_id": 1, "subject_id": 2, "attendance_date": pd.Timestamp("2026-01-02"), "status": "Absent", "department": "CSE"},
            {"student_id": 1, "subject_id": 3, "attendance_date": pd.Timestamp("2026-01-03"), "status": "Absent", "department": "CSE"},
            {"student_id": 1, "subject_id": 4, "attendance_date": pd.Timestamp("2026-01-04"), "status": "Present", "department": "CSE"},
        ]
        fb = FeatureBuilder(attendance=pd.DataFrame(rows))
        result = fb.build_one(student_id=1, as_of=date(2026, 1, 10))
        assert result["longest_absence_streak"] == 3

    def test_build_one_single_subject_variance_is_zero(self):
        rows = [
            {
                "student_id": 1,
                "subject_id": 1,
                "attendance_date": pd.Timestamp(f"2026-01-{d:02d}"),
                "status": "Present",
                "department": "CSE",
            }
            for d in range(1, 10)
        ]
        fb = FeatureBuilder(attendance=pd.DataFrame(rows))
        result = fb.build_one(student_id=1, as_of=date(2026, 1, 15))
        assert result["subject_variance"] == 0.0

    def test_build_one_dept_avg_includes_all_department_students(self):
        # Student 1: 30 present; student 2: 15 present + 15 absent → combined 75%
        df = pd.concat(
            [
                self._full_df(student_id=1, n_present=30, n_absent=0),
                self._full_df(student_id=2, n_present=15, n_absent=15),
            ],
            ignore_index=True,
        )
        fb = FeatureBuilder(attendance=df)
        result = fb.build_one(student_id=1, as_of=date(2026, 2, 28))
        assert result["dept_avg_pct"] == pytest.approx(75.0, abs=1.0)

    def test_build_many_skips_students_with_no_history(self, sample_df):
        fb = FeatureBuilder(attendance=sample_df)
        result = fb.build_many(student_ids=[1, 9999], as_of=date(2026, 3, 1))
        assert 9999 not in result["student_id"].tolist()
        assert 1 in result["student_id"].tolist()

    def test_build_many_returns_correct_columns(self, sample_df):
        fb = FeatureBuilder(attendance=sample_df)
        result = fb.build_many(student_ids=[1, 2, 3], as_of=date(2026, 3, 1))
        assert set(result.columns) == {"student_id", *FEATURE_COLUMNS}

    def test_build_many_empty_ids_returns_empty_dataframe(self, sample_df):
        fb = FeatureBuilder(attendance=sample_df)
        result = fb.build_many(student_ids=[], as_of=date(2026, 3, 1))
        assert result.empty
