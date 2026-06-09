"""POST /predict/detention-risk — score a batch of students for detention risk.

Called internally by Laravel's MLPredictionService (see
api/app/Services/MLPredictionService.php) — never exposed publicly.
"""
from datetime import date

from fastapi import APIRouter, HTTPException, Request

from app.data.loaders import load_attendance_history
from app.models.detention_risk import FEATURE_COLUMNS, FeatureBuilder
from app.schemas import DetentionRiskPrediction, DetentionRiskRequest, DetentionRiskResponse

router = APIRouter(prefix="/predict", tags=["predict"])


@router.post("/detention-risk", response_model=DetentionRiskResponse)
def predict_detention_risk(payload: DetentionRiskRequest, request: Request) -> DetentionRiskResponse:
    model = getattr(request.app.state, "detention_model", None)
    if model is None:
        raise HTTPException(status_code=503, detail="Detention-risk model is not loaded yet.")

    history = load_attendance_history(student_ids=payload.student_ids)

    # Clamp to the latest date that actually has marked attendance — never
    # forward past it (keeps feature windows leakage-free), but also keeps
    # "trailing N days" meaningful when today's attendance isn't marked yet
    # (e.g. the 02:00 cron runs before data entry, or seed data lags the clock).
    as_of = date.today()
    if not history.empty:
        as_of = min(as_of, history["attendance_date"].max().date())

    features = FeatureBuilder(attendance=history).build_many(payload.student_ids, as_of=as_of)

    predictions_df = model.predict(features).merge(features, on="student_id", how="left")
    scored_ids = set(predictions_df["student_id"].tolist())

    predictions = [
        DetentionRiskPrediction(
            student_id=row["student_id"],
            risk_score=row["risk_score"],
            predicted_detention=row["predicted_detention"],
            confidence=row["confidence"],
            features={col: row[col] for col in FEATURE_COLUMNS},
        )
        for row in predictions_df.to_dict(orient="records")
    ]

    return DetentionRiskResponse(
        model_version=model.version,
        predictions=predictions,
        skipped_student_ids=[sid for sid in payload.student_ids if sid not in scored_ids],
    )
