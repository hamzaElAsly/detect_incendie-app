"""API FastAPI de prédiction et de gestion des événements."""
import base64
from contextlib import asynccontextmanager
from datetime import datetime
import io
import logging
from typing import Annotated, Literal

from fastapi import Depends, FastAPI, File, HTTPException, Query, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from PIL import Image, UnidentifiedImageError
from pydantic import BaseModel, ConfigDict
from sqlalchemy.orm import Session

from database import init_db, session_dependency, test_connection
from logging_config import configure_logging
from models.fire_event import FireEvent
from repositories.fire_event_repository import FireEventRepository
from services.fire_detection_service import get_fire_detection_service

configure_logging()
logger = logging.getLogger(__name__)
StatusValue = Literal["detected", "confirmed", "false_alarm", "resolved"]


class EventResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)
    id: int
    detected_at: datetime
    image_path: str
    status: str
    alert_sent: bool
    confidence: float
    source: str
    created_at: datetime
    updated_at: datetime


class StatusUpdate(BaseModel):
    status: StatusValue


class AlertUpdate(BaseModel):
    alert_sent: bool


@asynccontextmanager
async def lifespan(_: FastAPI):
    logger.info("Démarrage de l'API")
    try:
        init_db()
    except Exception as exc:
        logger.error("Initialisation MySQL différée: %s", exc.__class__.__name__)
    yield
    logger.info("Arrêt de l'API")


app = FastAPI(title="Fire Detection API", lifespan=lifespan)
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_credentials=False,
                   allow_methods=["*"], allow_headers=["*"])


@app.get("/")
def root():
    return {"status": "API running"}


@app.get("/health")
def health():
    try:
        get_fire_detection_service()
        model_loaded = True
    except Exception:
        model_loaded = False
    database_connected, _ = test_connection()
    return {"status": "ok" if model_loaded else "degraded", "model_loaded": model_loaded,
            "database_connected": database_connected}


@app.post("/predict")
async def predict(image: UploadFile = File(...)):
    if image.content_type not in {"image/jpeg", "image/png"}:
        raise HTTPException(400, "Image only (JPG, PNG)")
    content = await image.read()
    if not content:
        raise HTTPException(400, "Empty image")
    if len(content) > 10 * 1024 * 1024:
        raise HTTPException(413, "Image too large (10 MB maximum)")
    try:
        candidate = Image.open(io.BytesIO(content)); candidate.verify()
        pil_image = Image.open(io.BytesIO(content)).convert("RGB")
    except (UnidentifiedImageError, OSError, ValueError) as exc:
        raise HTTPException(400, "Invalid or corrupted image") from exc
    result = get_fire_detection_service().predict_pil(pil_image)
    if result["is_fire"] and result["confidence"] >= 0.85:
        status, message = "fire_detected", "Incendie détecté avec forte certitude !"
    elif result["is_fire"]:
        status, message = "risk", "Risque d'incendie détecté, vigilance recommandée."
    else:
        status, message = "no_fire", "Probabilité faible d'incendie, situation normale."
    buffered = io.BytesIO(); pil_image.save(buffered, format="PNG")
    return {"success": True, "prediction": "Fire" if result["is_fire"] else "No Fire",
            "confidence": round(result["confidence"], 4), "status": status, "message": message,
            "predicted_index": result["predicted_index"], "probabilities": result["probabilities"],
            "image": "data:image/png;base64," + base64.b64encode(buffered.getvalue()).decode()}


@app.get("/events", response_model=list[EventResponse])
def list_events(session: Annotated[Session, Depends(session_dependency)],
                status: StatusValue | None = None, alert_sent: bool | None = None,
                source: str | None = None, limit: int = Query(100, ge=1, le=500),
                offset: int = Query(0, ge=0)):
    return FireEventRepository(session).list(status=status, alert_sent=alert_sent, source=source,
                                                  limit=limit, offset=offset)


@app.get("/events/{event_id}", response_model=EventResponse)
def get_event(event_id: int, session: Annotated[Session, Depends(session_dependency)]):
    event = FireEventRepository(session).get(event_id)
    if not event: raise HTTPException(404, "Événement introuvable")
    return event


@app.patch("/events/{event_id}/status", response_model=EventResponse)
def patch_status(event_id: int, payload: StatusUpdate,
                 session: Annotated[Session, Depends(session_dependency)]):
    event = FireEventRepository(session).update_status(event_id, payload.status)
    if not event: raise HTTPException(404, "Événement introuvable")
    logger.info("Statut de l'événement %s modifié en %s", event_id, payload.status)
    return event


@app.patch("/events/{event_id}/alert", response_model=EventResponse)
def patch_alert(event_id: int, payload: AlertUpdate,
                session: Annotated[Session, Depends(session_dependency)]):
    event = FireEventRepository(session).update_alert(event_id, payload.alert_sent)
    if not event: raise HTTPException(404, "Événement introuvable")
    logger.info("alert_sent de l'événement %s modifié en %s", event_id, payload.alert_sent)
    return event
