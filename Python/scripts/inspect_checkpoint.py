"""Inspection sûre du checkpoint sans le modifier."""
from pathlib import Path
import sys

import torch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
from services.image_preprocessing import load_model_metadata  # noqa: E402


def main() -> None:
    metadata = load_model_metadata()
    path = ROOT / metadata["checkpoint"]
    checkpoint = torch.load(path, map_location="cpu", weights_only=True)
    print(f"type = {type(checkpoint).__name__}")
    print(f"keys = {list(checkpoint.keys())}")
    for key, value in checkpoint.items():
        print(f"{key}: {tuple(value.shape)}")
    final_shape = tuple(checkpoint["classifier.weight"].shape)
    print(f"classifier.weight = {final_shape}")
    print(f"expected_num_classes = {metadata['architecture']['num_outputs']}")
    print(f"metadata_present_in_checkpoint = {not all(torch.is_tensor(v) for v in checkpoint.values())}")
    if final_shape != (metadata["architecture"]["num_outputs"], metadata["architecture"]["bottleneck_dim"]):
        raise SystemExit("INCOHÉRENCE: dernière couche incompatible avec les métadonnées")
    print("checkpoint_validation = OK")


if __name__ == "__main__":
    main()
