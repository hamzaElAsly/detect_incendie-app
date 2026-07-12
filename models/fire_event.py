"""Modèle SQLAlchemy d'un événement incendie."""
from datetime import datetime
from enum import Enum

from sqlalchemy import Boolean, DateTime, Float, String
from sqlalchemy.orm import Mapped, mapped_column

from database import Base


class EventStatus(str, Enum):
    DETECTED = "detected"
    CONFIRMED = "confirmed"
    FALSE_ALARM = "false_alarm"
    RESOLVED = "resolved"


class FireEvent(Base):
    __tablename__ = "fire_events"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    detected_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.now, index=True)
    image_path: Mapped[str] = mapped_column(String(500), nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default=EventStatus.DETECTED.value, index=True)
    alert_sent: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False, index=True)
    confidence: Mapped[float] = mapped_column(Float, nullable=False)
    source: Mapped[str] = mapped_column(String(30), nullable=False, index=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.now)
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.now, onupdate=datetime.now)
