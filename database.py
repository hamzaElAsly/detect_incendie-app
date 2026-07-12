"""Infrastructure SQLAlchemy à initialisation paresseuse pour MySQL."""
import logging
from typing import Generator

from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine
from sqlalchemy.orm import DeclarativeBase, Session, sessionmaker

from config import settings
from logging_config import configure_logging

configure_logging()
logger = logging.getLogger(__name__)


class Base(DeclarativeBase):
    pass


_engine: Engine | None = None
_session_factory: sessionmaker[Session] | None = None


def get_engine() -> Engine:
    global _engine, _session_factory
    if _engine is None:
        _engine = create_engine(settings.database_url(), pool_pre_ping=True, pool_recycle=3600)
        _session_factory = sessionmaker(bind=_engine, expire_on_commit=False)
    return _engine


def get_session() -> Session:
    get_engine()
    assert _session_factory is not None
    return _session_factory()


def session_dependency() -> Generator[Session, None, None]:
    session = get_session()
    try:
        yield session
    finally:
        session.close()


def test_connection() -> tuple[bool, str]:
    try:
        with get_engine().connect() as connection:
            connection.execute(text("SELECT 1"))
        logger.info("Connexion MySQL réussie")
        return True, "Connexion MySQL active"
    except Exception as exc:  # l'UI doit rester disponible sans MySQL
        logger.error("Connexion MySQL impossible: %s", exc.__class__.__name__)
        return False, f"MySQL indisponible ({exc.__class__.__name__})"


def init_db() -> None:
    from models.fire_event import FireEvent  # noqa: F401
    Base.metadata.create_all(bind=get_engine())
    logger.info("Tables MySQL initialisées")
