# Full training report

Date: 2026-08-23

```text
Training status: NOT STARTED
Reason: CPU-only machine; partial smoke run projected tens of hours for 2 epochs
Start: none
End: none
Duration: none
Estimated/actual finish: not applicable; no run started
Hardware: Intel Core i7-8665U, 4 cores/8 threads, ~15.8 GiB RAM
GPU: none; CUDA unavailable
Initial model: yolo11s.pt (smoke attempt only)
Recommended full baseline: yolo11n.pt
Dataset: fire_factory_dataset_clean_20260823
Train/val/test images: 14411 / 2812 / 3086
Epochs requested: 100
Epochs completed: 0
Early stopping: not applicable
Batch: 4 recommended for CPU
Image size: 640
Workers: 0
Optimizer: not run
Seed: 20260713
Best epoch: unavailable
best.pt: absent (`runs/fire_factory/baseline_yolo11n_cpu/weights/best.pt`)
last.pt: absent (`runs/fire_factory/baseline_yolo11n_cpu/weights/last.pt`)
Final metrics: unavailable
Incidents: original dataset had 70 JPEGs missing EOI; originals preserved and repaired derivative prepared
Resumes: none
Training completed: no
Evaluation ready: no
Blocking issues: no CUDA GPU; the CPU smoke projection is operationally excessive; no completed smoke gate
```

The training pipeline now enforces this gate programmatically: it refuses to
start unless `reports/SMOKE_TRAINING_PASS.json` exists with `{"passed": true}`.
The current status is recorded in `reports/SMOKE_TRAINING_STATUS.json` as
`passed=false`.

No full training was launched. This avoids falsely reporting a model and avoids
leaving a CPU job projected to run for days. A future GPU run can use the clean
dataset, `device=0`, a stable integer batch selected by a GPU smoke test, and
`resume=True` with `last.pt` after the first completed run.

Next command after moving to a suitable GPU machine:

```powershell
.\.venv\Scripts\python.exe scripts\fire_dataset_pipeline.py --dataset fire_factory_dataset_clean_20260823 train --experiments configs\yolo_experiments.json
```

Resume command, only after the stated `last.pt` path exists:

```powershell
.\.venv\Scripts\python.exe scripts\yolo_cli.py detect train resume model=runs/fire_factory/baseline_yolo11n_cpu/weights/last.pt
```
