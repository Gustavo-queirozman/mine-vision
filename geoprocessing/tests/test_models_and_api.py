import os

import pytest
from fastapi.testclient import TestClient
from pydantic import ValidationError

from app.main import app
from app.models import AnalysisRequest


def payload() -> dict:
    return {
        "job_id": 1,
        "company_id": 1,
        "monitoring_area_id": 2,
        "geometry": {"type": "Polygon", "coordinates": [[[0, 0], [1, 0], [1, 1], [0, 0]]]},
        "reference_image": {"id": 10},
        "current_image": {"id": 11},
    }


def test_analysis_request_validates_identifiers_and_threshold():
    request = AnalysisRequest.model_validate(payload())
    assert request.threshold == 0.18

    invalid = payload()
    invalid["threshold"] = 1.1
    with pytest.raises(ValidationError):
        AnalysisRequest.model_validate(invalid)


def test_health_endpoint_is_public():
    response = TestClient(app).get("/health")
    assert response.status_code == 200
    assert response.json()["pipeline"] == "copernicus_enabled"


def test_analyze_requires_configured_and_valid_internal_token(monkeypatch):
    monkeypatch.delenv("INTERNAL_TOKEN", raising=False)
    client = TestClient(app)
    assert client.post("/analyze", json=payload()).status_code == 503

    monkeypatch.setenv("INTERNAL_TOKEN", "test-token")
    assert client.post("/analyze", json=payload()).status_code == 401


def test_analyze_rejects_images_without_comparable_satellite(monkeypatch):
    monkeypatch.setenv("INTERNAL_TOKEN", "test-token")
    response = TestClient(app).post("/analyze", json=payload(), headers={"X-Internal-Token": "test-token"})
    assert response.status_code == 422
    assert "mesmo Sentinel" in response.json()["detail"]
