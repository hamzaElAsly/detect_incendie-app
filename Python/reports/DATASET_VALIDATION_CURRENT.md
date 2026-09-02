# Dataset validation current

Date: 2026-08-23

Dataset used for training: `fire_factory_dataset_clean_20260823`

- Result: **valid**
- Classes: `0=fire`, `1=smoke`
- Train: 14,411 images / 14,411 labels
- Validation: 2,812 images / 2,812 labels
- Test: 3,086 images / 3,086 labels
- Invalid annotations: 0
- Unpaired files: 0
- Image integrity: 14,411 train + 2,812 validation + 3,086 test images checked, 0 corrupt after repair
- JPEG repairs: 70 candidates verified; 70 originals preserved in `repair_staging_20260823/originals`
- Critical split leakage: 0 according to the existing grouped metadata validation
- Quasi-similar assets grouped: 373 (`pHash<=4`, gray RMSE<=8, color RMSE<=12, gray std>=15)
- Source assets audited: 20,568; excluded/quarantined: 259; original invalid annotations recorded: 331
- Active source metadata contains licenses and scene/video grouping fields
- Warning retained: no explicitly documented hard-negative corpus

The original prepared files were not deleted. Because the sandbox cannot rewrite
their ACL-protected paths, the repaired derivative is used for training.
