"""Tests for GET /health endpoint."""


class TestHealth:
    def test_health_returns_200(self, client_with_model):
        resp = client_with_model.get("/health")
        assert resp.status_code == 200

    def test_health_model_loaded_true_when_model_present(self, client_with_model):
        data = client_with_model.get("/health").json()
        assert data["model_loaded"] is True

    def test_health_model_version_matches_fixture(self, client_with_model):
        data = client_with_model.get("/health").json()
        assert data["model_version"] == "dummy_v1"

    def test_health_status_is_ok(self, client_with_model):
        data = client_with_model.get("/health").json()
        assert data["status"] == "ok"

    def test_health_model_loaded_false_when_no_model(self, client_no_model):
        data = client_no_model.get("/health").json()
        assert data["model_loaded"] is False

    def test_health_model_version_none_when_no_model(self, client_no_model):
        data = client_no_model.get("/health").json()
        assert data["model_version"] is None

    def test_health_still_200_when_no_model(self, client_no_model):
        resp = client_no_model.get("/health")
        assert resp.status_code == 200
