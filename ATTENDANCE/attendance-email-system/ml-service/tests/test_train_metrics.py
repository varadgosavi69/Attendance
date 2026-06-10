"""Tests for GET /train/metrics endpoint."""
import json
from pathlib import Path
from unittest.mock import MagicMock, patch


ENDPOINT = "/train/metrics"


def _mock_settings(model_dir: str) -> MagicMock:
    m = MagicMock()
    m.model_dir = model_dir
    return m


class TestTrainMetrics:
    def test_returns_404_when_no_metrics_file(self, client_no_model, tmp_path):
        with patch("app.routes.train.get_settings", return_value=_mock_settings(str(tmp_path))):
            resp = client_no_model.get(ENDPOINT)
        assert resp.status_code == 404

    def test_returns_404_detail_message(self, client_no_model, tmp_path):
        with patch("app.routes.train.get_settings", return_value=_mock_settings(str(tmp_path))):
            data = client_no_model.get(ENDPOINT).json()
        assert "metrics.json" in data["detail"].lower() or "metrics" in data["detail"].lower()

    def test_returns_200_when_metrics_file_exists(self, client_no_model, tmp_path):
        metrics = {"accuracy": 0.95, "precision": 0.92, "recall": 0.88}
        (tmp_path / "metrics.json").write_text(json.dumps(metrics))
        with patch("app.routes.train.get_settings", return_value=_mock_settings(str(tmp_path))):
            resp = client_no_model.get(ENDPOINT)
        assert resp.status_code == 200

    def test_returns_correct_metrics_content(self, client_no_model, tmp_path):
        metrics = {"accuracy": 0.95, "f1": 0.91}
        (tmp_path / "metrics.json").write_text(json.dumps(metrics))
        with patch("app.routes.train.get_settings", return_value=_mock_settings(str(tmp_path))):
            data = client_no_model.get(ENDPOINT).json()
        assert data["accuracy"] == 0.95
        assert data["f1"] == 0.91

    def test_works_regardless_of_model_loaded_state(self, client_with_model, tmp_path):
        metrics = {"roc_auc": 0.99}
        (tmp_path / "metrics.json").write_text(json.dumps(metrics))
        with patch("app.routes.train.get_settings", return_value=_mock_settings(str(tmp_path))):
            data = client_with_model.get(ENDPOINT).json()
        assert data["roc_auc"] == 0.99
