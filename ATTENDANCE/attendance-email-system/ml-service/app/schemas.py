"""Pydantic request/response models for the ML service's HTTP API."""
from pydantic import BaseModel, Field


class DetentionRiskRequest(BaseModel):
    student_ids: list[int] = Field(..., min_length=1, examples=[[1, 2, 3]])


class DetentionRiskPrediction(BaseModel):
    student_id: int
    risk_score: float = Field(..., ge=0.0, le=1.0)
    predicted_detention: bool
    confidence: float = Field(..., ge=0.0, le=1.0)
    features: dict[str, float] = Field(
        ..., description="The feature values the prediction was computed from — "
                         "stored verbatim as `features_snapshot` by Laravel.",
    )


class DetentionRiskResponse(BaseModel):
    model_version: str
    predictions: list[DetentionRiskPrediction]
    skipped_student_ids: list[int] = Field(
        default_factory=list,
        description="Requested IDs with no attendance history to build features from.",
    )
