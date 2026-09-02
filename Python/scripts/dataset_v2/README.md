# Dataset V2 preparation

Run from `Python` with the project virtual environment:

```powershell
.\.venv\Scripts\python.exe scripts\dataset_v2\pipeline.py run
```

The pipeline discovers exactly two sources below `fire_factory_dataset_v2_raw`,
reads their `data.yaml` files, and writes a new `fire_factory_dataset_v2` tree.
It never writes to a raw source or to `fire_factory_dataset_clean_20260823`.
If the requested output already contains data, a timestamped sibling is chosen
instead of deleting or overwriting the previous result. Use `--output` to name
an explicit new run directory.

`mapping.yaml` is part of the reproducibility contract. Class IDs are checked
against each source YAML before a label can be mapped. The default policy sends
empty labels, `Bat lua`, and `Candle` to `manual_review`; `fake-fire` is an
explicit hard-negative class. No YOLO training or fine-tuning command is run.

Reports are written both under the run's `reports/` directory and the final
dataset's `reports/` directory. The final `data.yaml` uses `path: .`, so its
paths resolve relative to the YAML's own dataset root.
