# YOLO Windows commands

From `Python`:

```powershell
.\.venv\Scripts\Activate.ps1
yolo version
```

If activation or PATH is unavailable, call the venv executable directly:

```powershell
.\.venv\Scripts\yolo.exe detect train data=fire_factory_dataset_clean_20260823/data.yaml model=yolo11n.pt epochs=100 imgsz=640 batch=4 workers=0 device=cpu project="$PWD\runs\fire_factory" name=baseline_yolo11n_cpu
```

The audited machine has no CUDA-capable PyTorch device; keep `device=cpu`
unless a new hardware check proves otherwise.

On this installation, the generated `yolo.exe` launcher exits silently with
code 1 and PowerShell activation is disabled. The tested fallback is:

```powershell
.\.venv\Scripts\python.exe scripts\yolo_cli.py version
```

After a real two-epoch smoke run, verify its artifacts before enabling full
training:

```powershell
.\.venv\Scripts\python.exe scripts\verify_smoke_run.py --run runs\fire_factory\sanity_check --write-pass
```
