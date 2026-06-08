"""GET /train/metrics — surface the latest training run's evaluation metrics.

Training itself runs out-of-band (cron-driven script — see /ml-service/train.py
and SCALABLE_ARCHITECTURE.md §8 "Training Pipeline"); this just lets the API
and any monitoring read what the currently-deployed model scored on its
held-out validation split, without re-running training over HTTP.
"""
import json
from pathlib import Path

from fastapi import APIRouter, HTTPException

from app.config import get_settings

router = APIRouter(prefix="/train", tags=["train"])


@router.get("/metrics")
def latest_metrics() -> dict:
    metrics_path = Path(get_settings().model_dir) / "metrics.json"

    if not metrics_path.exists():
        raise HTTPException(status_code=404, detail="No training run has produced metrics.json yet.")

    return json.loads(metrics_path.read_text())
