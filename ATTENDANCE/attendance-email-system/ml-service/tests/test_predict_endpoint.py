"""Tests for POST /predict/detention-risk endpoint."""
from unittest.mock import patch

import pandas as pd
import pytest


ENDPOINT = "/predict/detention-risk"


def _empty_history() -> pd.DataFrame:
    return pd.DataFrame(
        columns=["student_id", "subject_id", "attendance_date", "status", "department"]
    )


class TestPredictDetentionRiskValid:
    def test_valid_request_returns_200(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            resp = client_with_model.post(ENDPOINT, json={"student_ids": [1, 2, 3]})
        assert resp.status_code == 200

    def test_response_contains_required_top_level_keys(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1]}).json()
        assert {"model_version", "predictions", "skipped_student_ids"} <= data.keys()

    def test_model_version_matches_fixture(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1]}).json()
        assert data["model_version"] == "dummy_v1"

    def test_known_student_appears_in_predictions(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1]}).json()
        scored_ids = [p["student_id"] for p in data["predictions"]]
        assert 1 in scored_ids

    def test_prediction_risk_score_in_valid_range(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1, 2]}).json()
        for pred in data["predictions"]:
            assert 0.0 <= pred["risk_score"] <= 1.0
            assert 0.0 <= pred["confidence"] <= 1.0

    def test_prediction_includes_feature_snapshot(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1]}).json()
        assert len(data["predictions"]) > 0
        features = data["predictions"][0]["features"]
        assert "attendance_pct" in features
        assert "longest_absence_streak" in features

    def test_unknown_student_lands_in_skipped(self, client_with_model, sample_df):
        with patch("app.routes.predict.load_attendance_history", return_value=sample_df):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [1, 9999]}).json()
        assert 9999 in data["skipped_student_ids"]
        assert any(p["student_id"] == 1 for p in data["predictions"])

    def test_all_unknown_students_returns_empty_predictions(self, client_with_model):
        with patch("app.routes.predict.load_attendance_history", return_value=_empty_history()):
            data = client_with_model.post(ENDPOINT, json={"student_ids": [8888, 9999]}).json()
        assert data["predictions"] == []
        assert set(data["skipped_student_ids"]) == {8888, 9999}


class TestPredictDetentionRiskInvalid:
    def test_empty_student_ids_returns_422(self, client_with_model):
        resp = client_with_model.post(ENDPOINT, json={"student_ids": []})
        assert resp.status_code == 422

    def test_missing_student_ids_key_returns_422(self, client_with_model):
        resp = client_with_model.post(ENDPOINT, json={})
        assert resp.status_code == 422

    def test_string_student_ids_returns_422(self, client_with_model):
        resp = client_with_model.post(ENDPOINT, json={"student_ids": ["abc", "xyz"]})
        assert resp.status_code == 422

    def test_model_not_loaded_returns_503(self, client_no_model):
        resp = client_no_model.post(ENDPOINT, json={"student_ids": [1]})
        assert resp.status_code == 503
