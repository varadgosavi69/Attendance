"""Shared pytest fixtures for the ml-service test suite."""
from datetime import date, timedelta
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import pytest
from fastapi.testclient import TestClient
from xgboost import XGBClassifier

FIXTURES_DIR = Path(__file__).parent / "fixtures"
DUMMY_MODEL_PATH = FIXTURES_DIR / "dummy_model.joblib"

FEATURE_COLUMNS = [
    "attendance_pct",
    "trend_7d",
    "trend_14d",
    "trend_30d",
    "dept_avg_pct",
    "subject_variance",
    "longest_absence_streak",
]


@pytest.fixture(scope="session")
def dummy_xgb_classifier():
    """Tiny XGBoost classifier trained on synthetic data, saved to fixtures/."""
    FIXTURES_DIR.mkdir(exist_ok=True)

    rng = np.random.default_rng(42)
    # 7 features matching FEATURE_COLUMNS order
    X = rng.random((60, 7)).astype(np.float32)
    # Proxy label: detained when attendance_pct (col 0, scaled 0-1) < 0.75
    y = (X[:, 0] < 0.75).astype(int)

    clf = XGBClassifier(
        n_estimators=3,
        max_depth=2,
        random_state=42,
        eval_metric="logloss",
    )
    clf.fit(X, y)
    joblib.dump(clf, DUMMY_MODEL_PATH)
    return clf


@pytest.fixture(scope="session")
def dummy_model(dummy_xgb_classifier):
    """DetentionRiskModel wrapping the session-scoped dummy classifier."""
    from app.models.detention_risk import DetentionRiskModel

    return DetentionRiskModel(model=dummy_xgb_classifier, version="dummy_v1")


@pytest.fixture(scope="session")
def dummy_model_path(dummy_xgb_classifier):
    """Path to the saved dummy model file."""
    return DUMMY_MODEL_PATH


def _make_attendance_df(
    student_ids: list[int],
    start_date: date,
    n_days: int = 60,
    department: str = "CSE",
    absent_ratio: float = 0.20,
) -> pd.DataFrame:
    rng = np.random.default_rng(42)
    rows = []
    for sid in student_ids:
        for day in range(n_days):
            d = start_date + timedelta(days=day)
            status = "Absent" if rng.random() < absent_ratio else "Present"
            rows.append(
                {
                    "student_id": sid,
                    "subject_id": (day % 4) + 1,
                    "attendance_date": pd.Timestamp(d),
                    "status": status,
                    "department": department,
                }
            )
    return pd.DataFrame(rows)


@pytest.fixture
def sample_df():
    """Synthetic attendance for students 1-3, 60 days from 2026-01-01, ~80% present."""
    return _make_attendance_df([1, 2, 3], date(2026, 1, 1), n_days=60)


@pytest.fixture
def client_with_model(dummy_model):
    """TestClient with a dummy DetentionRiskModel on app.state."""
    from app.main import app

    app.state.detention_model = dummy_model
    yield TestClient(app)
    app.state.detention_model = None


@pytest.fixture
def client_no_model():
    """TestClient with no model loaded (simulates startup failure / model missing)."""
    from app.main import app

    app.state.detention_model = None
    yield TestClient(app)
