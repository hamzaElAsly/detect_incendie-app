# Smoke training report

Date: 2026-08-23

## Result

**NOT PASSED / NOT COMPLETED.** This is an honest partial run report; no
training success or metric is claimed.

The first attempt failed before training because `data.yaml` used `path: .`,
which made Ultralytics search for `Python/images/val`. The portable path was
corrected to `path: fire_factory_dataset` for the original dataset and the
clean derivative uses `path: fire_factory_dataset_clean_20260823`.

A second attempt loaded YOLO11s and the dataset on CPU, then was stopped after
the first epoch had only reached batch `2/3597`. It did not complete 2 epochs,
produce validation metrics, or produce `best.pt`/`last.pt`.

```text
Device: CPU
Model: yolo11s.pt
Requested epochs: 2
Image size: 640
Batch: 4
Workers: 0
Dataset: original path attempt; clean derivative prepared afterward
Images discovered by YOLO: 14411 train + 2812 validation
RAM/VRAM: ~15.8 GiB system RAM; VRAM not applicable
Observed progress: epoch 1, batch 2/3597
Observed batch timing: 11.1 s first batch, then 40.4 s/it and 21.5 s/it in the next output
Result: interrupted before epoch completion
Losses: unavailable at epoch level
Validation: not completed
NaN/CUDA/OOM check: no CUDA/OOM/NaN was observed before interruption; this is not a success criterion
Artifacts: early plots and args only; no results.csv, best.pt, or last.pt
```

Using the observed `21.5 s/it` as a rough lower-bound extrapolation gives
approximately 21.5 hours per epoch and 43 hours for two epochs, before
validation overhead. This is a measured feasibility signal, not a completed
benchmark. A 100-epoch CPU run should not be started automatically.

Artifacts currently present are only the run arguments and early batch plots:
`runs/fire_factory/sanity_check/args.yaml`, `labels.jpg`, and
`train_batch0.jpg` through `train_batch2.jpg`. No metrics or weights exist.

The artifact verifier was run against this directory and correctly returned
`passed=false`: `results.csv`, `best.pt`, and `last.pt` are missing. Therefore
no `SMOKE_TRAINING_PASS.json` marker exists.
