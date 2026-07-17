"""Interface Streamlit : upload, webcam et historique MySQL."""
from datetime import date, datetime
import logging
from pathlib import Path
import time

import cv2
from PIL import Image, UnidentifiedImageError
import streamlit as st

from config import PROJECT_ROOT, settings
from database import get_session, init_db, test_connection
from logging_config import configure_logging
from models.fire_event import EventStatus
from repositories.fire_event_repository import FireEventRepository
from services.capture_service import save_capture
from services.detection_gate import DetectionGate
from services.fire_detection_service import get_fire_detection_service

configure_logging(); logger = logging.getLogger(__name__)
st.set_page_config(page_title="Fire Detection AI", page_icon="🔥", layout="wide")
logger.info("Démarrage de l'application Streamlit")


@st.cache_resource
def detection_service():
    return get_fire_detection_service()


@st.cache_data(ttl=5, show_spinner=False)
def cached_database_status() -> tuple[bool, str]:
    return test_connection()


def database_status() -> bool:
    connected, message = cached_database_status()
    (st.success if connected else st.warning)(message)
    return connected


def upload_page() -> None:
    st.header("Détection à partir d'une image")
    uploaded = st.file_uploader("Upload an image (jpg / png)", type=["jpg", "jpeg", "png"])
    if uploaded is None: return
    try:
        image = Image.open(uploaded); image.verify(); uploaded.seek(0)
        image = Image.open(uploaded).convert("RGB")
    except (UnidentifiedImageError, OSError, ValueError):
        st.error("Le fichier envoyé n'est pas une image JPG ou PNG valide."); return
    st.image(image, caption="Image analysée", width="stretch")
    result = detection_service().predict_pil(image)
    label = "🔥 Fire" if result["is_fire"] else "✅ No Fire"
    st.subheader("Résultat de la prédiction")
    st.write(f"**Classe prédite : {label}**"); st.write(f"**Confiance : {result['confidence']:.2f}**")
    st.progress(result["confidence"])
    if result["confidence"] < 0.6: st.warning("Le modèle est incertain sur cette image.")
    elif result["is_fire"]: st.error("🔥 Incendie détecté !")
    else: st.success("✅ Aucun incendie détecté.")


def release_camera() -> None:
    camera = st.session_state.pop("camera", None)
    if camera is not None: camera.release()
    st.session_state.camera_active = False
    logger.info("Fermeture de la webcam")


def webcam_page() -> None:
    st.header("Détection en temps réel")
    connected = database_status()
    st.caption(f"Seuil événement {settings.fire_event_threshold:.0%} · {settings.required_positive_frames} frames positives · cooldown {settings.detection_cooldown_seconds}s")
    st.session_state.setdefault("camera_active", False); st.session_state.setdefault("session_events", 0)
    if "gate" not in st.session_state:
        st.session_state.gate = DetectionGate(settings.fire_event_threshold,
                                               settings.required_positive_frames,
                                               settings.detection_cooldown_seconds)
    start_col, stop_col, metric_col = st.columns(3)
    if start_col.button("Démarrer la caméra", disabled=st.session_state.camera_active):
        camera = cv2.VideoCapture(0)
        if not camera.isOpened():
            camera.release(); logger.error("Ouverture de la webcam impossible")
            st.error("Impossible d'ouvrir la webcam (index 0). Vérifiez les permissions Windows.")
        else:
            st.session_state.camera = camera; st.session_state.camera_active = True
            st.session_state.session_events = 0; logger.info("Ouverture de la webcam")
    if stop_col.button("Arrêter la caméra", disabled=not st.session_state.camera_active): release_camera()
    metric_col.metric("Événements de la session", st.session_state.session_events)
    frame_slot, result_slot = st.empty(), st.empty()
    if not st.session_state.camera_active: return
    camera = st.session_state.get("camera")
    ok, frame = camera.read() if camera is not None else (False, None)
    if not ok:
        st.error("La webcam ne fournit plus d'image."); logger.error("Erreur de lecture webcam"); release_camera(); return
    try:
        raw_frame = frame.copy()
        result = detection_service().predict_frame(raw_frame)
        confirmed = st.session_state.gate.observe(result["is_fire"], result["confidence"])
        color = (0, 0, 255) if result["is_fire"] else (0, 180, 0)
        text = f"{result['label']} {result['confidence']:.1%}"
        cv2.putText(frame, text, (20, 40), cv2.FONT_HERSHEY_SIMPLEX, 1, color, 2)
        frame_slot.image(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB), channels="RGB", width="stretch")
        if result["is_fire"]:
            result_slot.error(text)
        else:
            result_slot.success(text)
        if confirmed:
            relative_path = save_capture(raw_frame)
            logger.warning("Incendie confirmé: confiance %.4f", result["confidence"])
            if connected:
                try:
                    with get_session() as session:
                        FireEventRepository(session).create(relative_path, result["confidence"], "webcam")
                    st.session_state.session_events += 1; logger.info("Événement webcam enregistré")
                except Exception as exc:
                    logger.error("Erreur d'enregistrement: %s", exc.__class__.__name__)
                    st.error(f"Capture sauvegardée, mais MySQL est indisponible ({exc.__class__.__name__}).")
            else:
                logger.error("Détection locale non enregistrée en base: MySQL indisponible")
                st.error("Capture sauvegardée localement; événement non enregistré car MySQL est indisponible.")
    except Exception as exc:
        logger.exception("Erreur durant l'inférence webcam")
        st.error(f"Erreur webcam: {exc}")
        release_camera()
        return
    time.sleep(0.05); st.rerun()


def history_page() -> None:
    st.header("Historique des détections")
    if not database_status(): return
    if st.button("Actualiser"): st.rerun()
    col1, col2, col3, col4 = st.columns(4)
    use_date = col1.checkbox("Filtrer par date")
    selected_date = col1.date_input("Date", date.today(), disabled=not use_date)
    status = col2.selectbox("Statut", ["Tous"] + [item.value for item in EventStatus])
    alert = col3.selectbox("Alerte envoyée", ["Toutes", "Oui", "Non"])
    source = col4.selectbox("Source", ["Toutes", "webcam", "upload", "api"])
    try:
        with get_session() as session:
            repo = FireEventRepository(session)
            events = repo.list(status=None if status == "Tous" else status,
                               alert_sent=None if alert == "Toutes" else alert == "Oui",
                               source=None if source == "Toutes" else source,
                               detected_date=selected_date if use_date else None, limit=500)
            rows = [{"ID": e.id, "Date": e.detected_at, "Image": e.image_path,
                     "Confiance": round(e.confidence, 4), "Statut": e.status,
                     "Alerte envoyée": e.alert_sent, "Source": e.source} for e in events]
            st.dataframe(rows, width="stretch", hide_index=True)
            if not events: return
            event_id = st.selectbox("Événement à gérer", [e.id for e in events])
            event = next(e for e in events if e.id == event_id)
            image_path = PROJECT_ROOT / Path(event.image_path)
            if image_path.is_file(): st.image(str(image_path), caption=event.image_path, width=500)
            new_status = st.selectbox("Nouveau statut", [item.value for item in EventStatus],
                                      index=[item.value for item in EventStatus].index(event.status))
            alert_value = st.checkbox("Alerte envoyée", value=event.alert_sent)
            if st.button("Enregistrer les modifications"):
                repo.update_status(event.id, new_status); repo.update_alert(event.id, alert_value)
                logger.info("Événement %s mis à jour depuis Streamlit", event.id); st.success("Événement mis à jour."); st.rerun()
    except Exception as exc:
        logger.error("Lecture historique impossible: %s", exc.__class__.__name__)
        st.error(f"Impossible de lire l'historique ({exc.__class__.__name__}).")


st.title("🔥 Fire Detection using Deep Learning")
page = st.sidebar.radio("Navigation", ["Upload d'image", "Détection en temps réel", "Historique des détections"])
if page != "Détection en temps réel" and st.session_state.get("camera_active"): release_camera()
if page == "Upload d'image": upload_page()
elif page == "Détection en temps réel": webcam_page()
else: history_page()
st.markdown("---"); st.caption("Projet Deep Learning — Détection d'incendie | Master 2 Data Science")
