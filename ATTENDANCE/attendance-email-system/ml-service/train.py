"""Detention-risk model training pipeline (SCALABLE_ARCHITECTURE.md §8 — cron-driven).

Usage:
    python train.py [--months-back 12]

Steps:
  1. Pull the last N months ("~2 semesters") of attendance history from the
     read replica.
  2. Build one (student, month) sample per month a student has attendance data:
     features as of that month's last marked date (no leakage — only data on
     or before that date is used) + a label of whether *that month's*
     attendance % fell below the detention threshold.
  3. 80/20 stratified train/validation split.
  4. Train an XGBoost classifier.
  5. Evaluate precision / recall / F1 / AUC on the held-out validation split.
  6. Save the model (joblib, atomic-swappable by filename) and append the
     run's metrics to trained_models/metrics.json.
"""
from __future__ import annotations

import argparse
import json
from datetime import datetime
from pathlib import Path

import joblib
import pandas as pd
from sklearn.metrics import f1_score, precision_score, recall_score, roc_auc_score
from sklearn.model_selection import train_test_split
from xgboost import XGBClassifier

from app.config import get_settings
from app.data.loaders import load_attendance_history
from app.models.detention_risk import FEATURE_COLUMNS, FeatureBuilder

SEMESTER_MONTHS = 6


def build_training_frame(history: pd.DataFrame, threshold: float) -> pd.DataFrame:
    """One row per (student, month with attendance data): features as of that
    month's last marked date, labelled with whether *that* month's attendance
    percentage fell below `threshold` (mirrors DetentionService::calculateMonthly).
    """
    builder = FeatureBuilder(attendance=history)
    tagged = history.assign(year_month=history["attendance_date"].dt.to_period("M"))

    rows: list[dict] = []
    for (student_id, year_month), month_rows in tagged.groupby(["student_id", "year_month"]):
        total = len(month_rows)
        present = int((month_rows["status"] == "Present").sum())
        monthly_pct = (present / total * 100) if total else 0.0

        features = builder.build_one(student_id, as_of=month_rows["attendance_date"].max().date())
        if features is None:
            continue

        rows.append({
            "student_id": student_id,
            "year_month": str(year_month),
            **features,
            "detained": int(monthly_pct < threshold),
        })

    return pd.DataFrame(rows)


def main() -> None:
    parser = argparse.ArgumentParser(description="Train the detention-risk XGBoost classifier.")
    parser.add_argument(
        "--months-back", type=int, default=2 * SEMESTER_MONTHS,
        help="How many months of history to pull (default: ~2 semesters = 12 months).",
    )
    args = parser.parse_args()

    settings = get_settings()
    since = (pd.Timestamp.today().normalize() - pd.DateOffset(months=args.months_back)).date()

    print(f"[1/5] Loading attendance history since {since} from the read replica...")
    history = load_attendance_history(since=since)
    print(f"      {len(history):,} attendance rows across {history['student_id'].nunique()} students")

    print(f"[2/5] Building per-student-month samples + detention labels (monthly % < {settings.detention_threshold})...")
    frame = build_training_frame(history, settings.detention_threshold)
    positives = int(frame["detained"].sum())
    print(f"      {len(frame):,} (student, month) samples — {positives} detained / {len(frame) - positives} not-detained")

    if frame["detained"].nunique() < 2:
        raise SystemExit(
            "Training data has only one class present (all months detained, or none) — "
            "cannot train/evaluate a classifier. Need history spanning both outcomes."
        )

    X, y = frame[FEATURE_COLUMNS], frame["detained"]

    print("[3/5] Splitting 80/20 train/validation (stratified)...")
    X_train, X_val, y_train, y_val = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
    print(f"      train={len(X_train)}  validation={len(X_val)}")

    print("[4/5] Training XGBoost classifier...")
    model = XGBClassifier(
        n_estimators=200,
        max_depth=4,
        learning_rate=0.1,
        subsample=0.9,
        colsample_bytree=0.9,
        eval_metric="logloss",
        random_state=42,
    )
    model.fit(X_train, y_train)

    print("[5/5] Evaluating on the held-out validation split...")
    val_proba = model.predict_proba(X_val)[:, 1]
    val_pred = (val_proba >= 0.5).astype(int)

    metrics = {
        "model_version": settings.model_version,
        "trained_at": datetime.now().isoformat(timespec="seconds"),
        "samples": {"total": len(frame), "train": len(X_train), "validation": len(X_val)},
        "class_balance": {"detained": positives, "not_detained": len(frame) - positives},
        "metrics": {
            "precision": round(float(precision_score(y_val, val_pred, zero_division=0)), 4),
            "recall":    round(float(recall_score(y_val, val_pred, zero_division=0)), 4),
            "f1":        round(float(f1_score(y_val, val_pred, zero_division=0)), 4),
            "auc":       round(float(roc_auc_score(y_val, val_proba)), 4) if y_val.nunique() > 1 else None,
        },
    }

    model_dir = Path(settings.model_dir)
    model_dir.mkdir(parents=True, exist_ok=True)

    model_path = model_dir / f"{settings.model_version}.joblib"
    joblib.dump(model, model_path)

    metrics_path = model_dir / "metrics.json"
    log = json.loads(metrics_path.read_text()) if metrics_path.exists() else []
    log = log if isinstance(log, list) else [log]
    log.append(metrics)
    metrics_path.write_text(json.dumps(log, indent=2))

    print(f"\nSaved model   -> {model_path}")
    print(f"Saved metrics -> {metrics_path}")
    print(json.dumps(metrics["metrics"], indent=2))


if __name__ == "__main__":
    main()
