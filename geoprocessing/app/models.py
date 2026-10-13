from typing import Any, Literal

from pydantic import BaseModel, Field


class Geometry(BaseModel):
    type: Literal["Polygon", "MultiPolygon"]
    coordinates: list[Any] = Field(min_length=1)


class AnalysisRequest(BaseModel):
    job_id: int = Field(gt=0)
    company_id: int = Field(gt=0)
    monitoring_area_id: int = Field(gt=0)
    geometry: Geometry
    reference_image: dict[str, Any]
    current_image: dict[str, Any]
    analysis_type: Literal["CHANGE_DETECTION", "GOLD_POTENTIAL"] = "CHANGE_DETECTION"
    threshold: float = Field(default=0.18, gt=0, le=1)


class AnalysisResult(BaseModel):
    affected_area_hectares: float = Field(ge=0)
    confidence: float = Field(ge=0, le=100)
    severity: Literal["LOW", "MEDIUM", "HIGH", "CRITICAL"]
    geometry: Geometry | None
    metadata: dict[str, Any]
    images: list[dict[str, Any]] = Field(default_factory=list)
    indices: list[dict[str, Any]] = Field(default_factory=list)
