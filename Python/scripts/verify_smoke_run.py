"""Verify a completed two-epoch YOLO smoke run before opening the train gate.

The script only writes a pass marker when the run directory contains the
expected two result rows, finite losses/metrics, and both checkpoint files.
"""
from __future__ import annotations

import argparse
import csv
import json
import math
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
REQUIRED_COLUMNS = (
    "train/box_loss",
    "train/cls_loss",
    "train/dfl_loss",
    "metrics/precision(B)",
    "metrics/recall(B)",
    "metrics/mAP50(B)",
    "metrics/mAP50-95(B)",
)


def args_value(text: str, key: str) -> str | None:
    match = re.search(rf"(?m)^{re.escape(key)}:\s*(.+?)\s*$", text)
    return match.group(1).strip() if match else None


def verify(run_dir: Path) -> dict[str, object]:
    checks: dict[str, bool] = {}
    reasons: list[str] = []
    args_path = run_dir / "args.yaml"
    results_path = run_dir / "results.csv"
    best_path = run_dir / "weights" / "best.pt"
    last_path = run_dir / "weights" / "last.pt"
    args_text = args_path.read_text(encoding="utf-8") if args_path.is_file() else ""
    checks["args_present"] = args_path.is_file()
    checks["requested_epochs_2"] = args_value(args_text, "epochs") == "2"
    checks["results_present"] = results_path.is_file()
    checks["best_present"] = best_path.is_file()
    checks["last_present"] = last_path.is_file()
    if not checks["args_present"]:
        reasons.append("args.yaml missing")
    if not checks["requested_epochs_2"]:
        reasons.append("args.yaml does not declare epochs: 2")
    if not checks["results_present"]:
        reasons.append("results.csv missing")
    if not checks["best_present"] or not checks["last_present"]:
        reasons.append("best.pt and last.pt are both required")

    rows: list[dict[str, str]] = []
    if results_path.is_file():
        with results_path.open(encoding="utf-8-sig", newline="") as stream:
            rows = list(csv.DictReader(stream))
    checks["two_result_rows"] = len(rows) == 2
    if not checks["two_result_rows"]:
        reasons.append(f"results.csv has {len(rows)} rows; exactly 2 required")
    columns = set(rows[0]) if rows else set()
    missing_columns = [column for column in REQUIRED_COLUMNS if column not in columns]
    checks["required_metrics_present"] = not missing_columns
    if missing_columns:
        reasons.append("missing result columns: " + ", ".join(missing_columns))
    finite = True
    if rows:
        for row in rows:
            for key, raw in row.items():
                if key == "epoch" or raw is None or not raw.strip():
                    continue
                try:
                    if not math.isfinite(float(raw)):
                        finite = False
                except ValueError:
                    continue
    checks["finite_numeric_values"] = finite
    if not finite:
        reasons.append("results.csv contains NaN or infinite numeric values")
    passed = all(checks.values())
    return {
        "passed": passed,
        "run": str(run_dir.resolve()),
        "checks": checks,
        "reasons": reasons,
        "result_rows": len(rows),
        "best_pt": str(best_path.resolve()),
        "last_pt": str(last_path.resolve()),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--run", type=Path, default=ROOT / "runs" / "fire_factory" / "sanity_check")
    parser.add_argument("--write-pass", action="store_true")
    args = parser.parse_args()
    result = verify(args.run.resolve())
    print(json.dumps(result, ensure_ascii=False, indent=2))
    if result["passed"] and args.write_pass:
        target = ROOT / "reports" / "SMOKE_TRAINING_PASS.json"
        target.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"pass_marker={target}")
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
