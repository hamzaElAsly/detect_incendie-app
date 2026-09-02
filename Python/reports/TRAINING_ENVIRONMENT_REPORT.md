# Training environment report

Date: 2026-08-23

| Field | Measured value |
|---|---|
| Python | 3.12.10 |
| Python executable | `Python/.venv/Scripts/python.exe` |
| Virtual environment | Present and used |
| Ultralytics | 8.4.95 |
| PyTorch | 2.13.0+cpu |
| Torchvision | 0.28.0+cpu |
| OpenCV | 5.0.0 |
| CPU | Intel Core i7-8665U 1.90GHz |
| Cores / logical processors | 4 / 8 |
| RAM | 16,976,740,352 bytes (~15.8 GiB) |
| GPU | None available through CUDA |
| CUDA available | `False` |
| NVIDIA driver / nvidia-smi | Not available (`nvidia-smi` not found) |
| Disk free | 170,878,283,776 bytes (~159.1 GiB) |

## Decision

```text
Training device: CPU
GPU available: False
CUDA available: False
GPU: none detected
VRAM: not applicable
RAM: ~15.8 GiB
Disk free: ~159.1 GiB
Recommended model: yolo11n.pt for a CPU baseline
Recommended batch: 4
Recommended workers: 0
Estimated feasibility: smoke/full CPU training is technically possible but operationally excessive
```

The installed `yolo.exe` is inside the venv, but this generated launcher exits
silently with code 1 on this machine. The global `yolo` command is also not
available because `Python/.venv/Scripts` is not on PATH. The tested reliable
command is `Python/.venv/Scripts/python.exe scripts/yolo_cli.py version`.
No system PATH or execution policy change was made.
