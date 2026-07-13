"""Audit reproductible du dataset sans modifier les images."""
from collections import Counter, defaultdict
import hashlib
from pathlib import Path
import statistics
import sys

from PIL import Image, UnidentifiedImageError
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
DATASET = ROOT / "data_set"
REPORT = ROOT / "DATASET_AUDIT.md"


def dhash(image: Image.Image, size: int = 8) -> int:
    gray = image.convert("L").resize((size + 1, size))
    pixels = np.asarray(gray)
    return sum(int(bit) << index for index, bit in enumerate((pixels[:, 1:] > pixels[:, :-1]).flat))


def main() -> None:
    files = sorted(path for path in DATASET.rglob("*") if path.is_file())
    counts: Counter[str] = Counter()
    subtypes: Counter[str] = Counter()
    dimensions: list[tuple[int, int]] = []
    invalid: list[str] = []
    exact: dict[str, list[str]] = defaultdict(list)
    perceptual: list[tuple[str, str, int]] = []
    hashes: list[tuple[str, int]] = []
    rgb_by_class: dict[str, list[np.ndarray]] = defaultdict(list)
    for path in files:
        relative = path.relative_to(ROOT).as_posix()
        label = path.parent.name
        counts[label] += 1
        subtypes[f"{label}/{path.stem.split(' (')[0]}"] += 1
        exact[hashlib.sha256(path.read_bytes()).hexdigest()].append(relative)
        try:
            with Image.open(path) as image:
                image.load(); rgb = image.convert("RGB")
                dimensions.append(rgb.size)
                hashes.append((relative, dhash(rgb)))
                rgb_by_class[label].append(np.asarray(rgb.resize((16, 16)), dtype=np.float32).mean((0, 1)))
        except (UnidentifiedImageError, OSError, ValueError) as exc:
            invalid.append(f"{relative}: {exc.__class__.__name__}")
    for index, (first, first_hash) in enumerate(hashes):
        for second, second_hash in hashes[index + 1:]:
            distance = (first_hash ^ second_hash).bit_count()
            if distance <= 2:
                perceptual.append((first, second, distance))
    duplicate_groups = [group for group in exact.values() if len(group) > 1]
    widths = [width for width, _ in dimensions]; heights = [height for _, height in dimensions]
    small = sum(width < 224 or height < 224 for width, height in dimensions)
    lines = [
        "# Audit du dataset", "", "## Volumétrie", "",
        f"- Total : {len(files)} fichiers; valides : {len(dimensions)}; invalides : {len(invalid)}.",
        *[f"- `{label}` : {count} ({count / len(files):.1%})." for label, count in sorted(counts.items())],
        *[f"- Sous-type `{name}` : {count}." for name, count in sorted(subtypes.items())],
        "", "## Dimensions et couleurs", "",
        f"- Largeur moyenne/médiane : {statistics.mean(widths):.1f}/{statistics.median(widths):.1f}px.",
        f"- Hauteur moyenne/médiane : {statistics.mean(heights):.1f}/{statistics.median(heights):.1f}px.",
        f"- Images avec au moins une dimension inférieure à 224px : {small}.",
    ]
    for label, values in sorted(rgb_by_class.items()):
        mean = np.mean(values, axis=0)
        lines.append(f"- Couleur RGB moyenne `{label}` (miniatures 16×16) : [{mean[0]:.1f}, {mean[1]:.1f}, {mean[2]:.1f}].")
    lines += ["", "## Intégrité et doublons", "", f"- Fichiers invalides : {len(invalid)}.",
              f"- Groupes de doublons binaires exacts : {len(duplicate_groups)}.",
              f"- Paires perceptuellement très proches (dHash distance ≤2) : {len(perceptual)}."]
    if invalid: lines += ["", *[f"- {item}" for item in invalid[:20]]]
    if duplicate_groups: lines += ["", "Exemples exacts:", *[f"- {group}" for group in duplicate_groups[:20]]]
    if perceptual: lines += ["", "Exemples proches:", *[f"- `{a}` ↔ `{b}` (distance {d})" for a,b,d in perceptual[:30]]]
    lines += [
        "", "## Risques de qualité", "",
        "- L'équilibre numérique 50/50 ne garantit pas la représentativité du monde réel.",
        "- `Fire` mélange flammes et fumée; `No_Fire` mélange feux artificiels et scènes dites sûres.",
        "- Le split aléatoire par fichier peut placer des scènes quasi identiques dans plusieurs ensembles.",
        "- Les scènes webcam ordinaires (personnes, murs, bureaux, éclairages intérieurs) ne constituent pas un corpus négatif explicite.",
        "- Les différences de couleur/fond peuvent devenir des raccourcis appris par le CNN.",
        "", "## Recommandations", "",
        "Regrouper les images par scène/source avant le split, contrôler manuellement les paires proches, ajouter des négatifs difficiles issus de vraies webcams, séparer les métriques feu/fumée et calibrer le modèle sur un jeu de validation indépendant.",
    ]
    REPORT.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"classes = {sorted(counts)}")
    print(f"counts = {dict(counts)}")
    print(f"invalid = {len(invalid)}")
    print(f"exact_duplicate_groups = {len(duplicate_groups)}")
    print(f"near_duplicate_pairs = {len(perceptual)}")
    print(f"report = {REPORT}")


if __name__ == "__main__":
    main()
