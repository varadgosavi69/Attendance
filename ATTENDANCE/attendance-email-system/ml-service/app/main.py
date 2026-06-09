"""FastAPI entry point — Phase 6 detention-risk ML microservice.

Loads the trained model once at startup and exposes it over HTTP for Laravel
to call (api/app/Services/MLPredictionService.php), keeping ML code/deploy
cadence isolated from the web app per SCALABLE_ARCHITECTURE.md §8.
"""
from contextlib import asynccontextmanager
from pathlib import Path

import sentry_sdk
from fastapi import FastAPI
from sentry_sdk.integrations.fastapi import FastApiIntegration
from sentry_sdk.integrations.starlette import StarletteIntegration

from app.config import get_settings
from app.models.detention_risk import DetentionRiskModel
from app.routes import predict, train


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()

    if settings.sentry_dsn:
        sentry_sdk.init(
            dsn=settings.sentry_dsn,
            environment=settings.environment,
            integrations=[
                StarletteIntegration(transaction_style="url"),
                FastApiIntegration(transaction_style="url"),
            ],
            traces_sample_rate=0.1,
            send_default_pii=False,
        )

    model_path = Path(settings.model_dir) / f"{settings.model_version}.joblib"

    app.state.detention_model = (
        DetentionRiskModel.load(str(model_path), settings.model_version)
        if model_path.exists()
        else None
    )

    yield


app = FastAPI(
    title="Attendance ML Service",
    description="Detention-risk prediction microservice (SCALABLE_ARCHITECTURE.md §8)",
    version="1.0.0",
    lifespan=lifespan,
)

app.include_router(predict.router)
app.include_router(train.router)


@app.get("/health", tags=["health"])
def health() -> dict:
    model = app.state.detention_model
    return {
        "status": "ok",
        "model_loaded": model is not None,
        "model_version": getattr(model, "version", None),
    }
