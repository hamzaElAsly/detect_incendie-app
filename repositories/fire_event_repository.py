"""Accès aux événements, isolé des interfaces Streamlit et FastAPI."""
from datetime import date, datetime, time

from sqlalchemy import Select, select
from sqlalchemy.orm import Session

from models.fire_event import EventStatus, FireEvent


class FireEventRepository:
    def __init__(self, session: Session):
        self.session = session

    def create(self, image_path: str, confidence: float, source: str,
               detected_at: datetime | None = None) -> FireEvent:
        event = FireEvent(image_path=image_path, confidence=confidence, source=source,
                          detected_at=detected_at or datetime.now())
        self.session.add(event)
        self.session.commit()
        self.session.refresh(event)
        return event

    def get(self, event_id: int) -> FireEvent | None:
        return self.session.get(FireEvent, event_id)

    def list(self, *, status: str | None = None, alert_sent: bool | None = None,
             source: str | None = None, detected_date: date | None = None,
             limit: int = 100, offset: int = 0) -> list[FireEvent]:
        query: Select[tuple[FireEvent]] = select(FireEvent)
        if status:
            query = query.where(FireEvent.status == status)
        if alert_sent is not None:
            query = query.where(FireEvent.alert_sent == alert_sent)
        if source:
            query = query.where(FireEvent.source == source)
        if detected_date:
            query = query.where(
                FireEvent.detected_at >= datetime.combine(detected_date, time.min),
                FireEvent.detected_at <= datetime.combine(detected_date, time.max),
            )
        query = query.order_by(FireEvent.detected_at.desc()).limit(limit).offset(offset)
        return list(self.session.scalars(query))

    def update_status(self, event_id: int, status: EventStatus | str) -> FireEvent | None:
        event = self.get(event_id)
        if event is None:
            return None
        value = status.value if isinstance(status, EventStatus) else status
        if value not in {item.value for item in EventStatus}:
            raise ValueError("Statut d'événement invalide")
        event.status = value
        self.session.commit()
        self.session.refresh(event)
        return event

    def update_alert(self, event_id: int, alert_sent: bool) -> FireEvent | None:
        event = self.get(event_id)
        if event is None:
            return None
        event.alert_sent = alert_sent
        self.session.commit()
        self.session.refresh(event)
        return event

    def delete(self, event_id: int) -> bool:
        event = self.get(event_id)
        if event is None:
            return False
        self.session.delete(event)
        self.session.commit()
        return True
