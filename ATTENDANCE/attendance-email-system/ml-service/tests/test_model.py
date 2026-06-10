"""Unit tests for DetentionRiskModel — load, predict, output shape."""
import pandas as pd
import pytest

from app.models.detention_risk import FEATURE_COLUMNS, DetentionRiskModel


def _features(*student_ids: int, **overrides) -> pd.DataFrame:
    """Build a minimal features DataFrame with sensible defaults."""
    defaults = {
        "attendance_pct": 80.0,
        "trend_7d": 0.0,
        "trend_14d": 0.0,
        "trend_30d": 0.0,
        "dept_avg_pct": 75.0,
        "subject_variance": 5.0,
        "longest_absence_streak": 1,
    }
    defaults.update(overrides)
    return pd.DataFrame([{"student_id": sid, **defaults} for sid in student_ids])


class TestDetentionRiskModelLoad:
    def test_load_returns_model_with_correct_version(self, dummy_model_path):
        model = DetentionRiskModel.load(str(dummy_model_path), "dummy_v1")
        assert model.version == "dummy_v1"

    def test_load_creates_callable_model(self, dummy_model_path):
        model = DetentionRiskModel.load(str(dummy_model_path), "dummy_v1")
        assert model.model is not None
        assert hasattr(model.model, "predict_proba")


class TestDetentionRiskModelPredict:
    def test_predict_returns_correct_columns(self, dummy_model):
        result = dummy_model.predict(_features(1))
        assert set(result.columns) == {"student_id", "risk_score", "predicted_detention", "confidence"}

    def test_predict_one_row_per_student(self, dummy_model):
        result = dummy_model.predict(_features(10, 20, 30))
        assert len(result) == 3
        assert set(result["student_id"].tolist()) == {10, 20, 30}

    def test_predict_risk_score_in_0_to_1(self, dummy_model):
        result = dummy_model.predict(_features(1, 2, 3, 4, 5))
        assert (result["risk_score"] >= 0.0).all()
        assert (result["risk_score"] <= 1.0).all()

    def test_predict_confidence_at_least_0_5(self, dummy_model):
        result = dummy_model.predict(_features(1, attendance_pct=50.0, longest_absence_streak=10))
        assert (result["confidence"] >= 0.5).all()
        assert (result["confidence"] <= 1.0).all()

    def test_predict_empty_dataframe_returns_empty(self, dummy_model):
        empty = pd.DataFrame(columns=["student_id"] + FEATURE_COLUMNS)
        result = dummy_model.predict(empty)
        assert result.empty
        assert set(result.columns) == {"student_id", "risk_score", "predicted_detention", "confidence"}

    def test_predict_detention_is_boolean(self, dummy_model):
        result = dummy_model.predict(_features(1))
        assert result["predicted_detention"].dtype == bool or result["predicted_detention"].isin([True, False]).all()
