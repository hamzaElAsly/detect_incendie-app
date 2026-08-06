from __future__ import annotations

import argparse
from dataclasses import replace

from .logging_config import configure_logging
from .main import EdgeAgent
from .settings import EdgeSettings


def main() -> None:
    parser = argparse.ArgumentParser(description="Detect Incendie Edge Agent")
    parser.add_argument("--dry-run", action="store_true", help="Force dry-run without API writes")
    parser.add_argument("--mock-detector", action="store_true", help="Run without loading YOLO weights")
    parser.add_argument("--source")
    parser.add_argument("--source-type", choices=["rtsp", "webcam", "file"])
    parser.add_argument("--config-check", action="store_true", help="Validate configuration and exit")
    args = parser.parse_args()
    settings = EdgeSettings()
    settings = replace(settings,
        dry_run=True if args.dry_run else settings.dry_run,
        mock_detector=True if args.mock_detector else settings.mock_detector,
        source=args.source or settings.source,
        source_type=args.source_type or settings.source_type)
    settings.validate()
    if args.config_check:
        print(f"configuration=valid dry_run={settings.dry_run} mock_detector={settings.mock_detector} model_business_validated={settings.model_business_validated}")
        return
    configure_logging(settings.log_dir / "edge-agent.log", settings.log_level,
                      settings.log_max_bytes, settings.log_backup_count)
    EdgeAgent(settings).run()


if __name__ == "__main__":
    main()
