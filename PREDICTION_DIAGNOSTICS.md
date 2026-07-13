# Diagnostic des prédictions

## Résumé

- Échantillon dataset stratifié et déterministe : 100 images (25 maximum par sous-type).
- Captures webcam normales ajoutées : 3.
- Accuracy approximative dataset : 96.00%.
- Accuracy avec mapping réel `0→fire, 1→no_fire` : 96.00%.
- Accuracy avec mapping inversé `0→no_fire, 1→fire` : 4.00%.
- Faux positifs (incluant webcam) : 4; faux négatifs : 3.
- Confiance moyenne : 0.9272.

## Exemples les plus problématiques

- `storage/detections/fire_20260712_224650_007999.jpg` attendu `no_fire`, prédit `fire` à 99.9523%.
- `storage/detections/fire_20260712_224634_979383.jpg` attendu `no_fire`, prédit `fire` à 99.8403%.
- `storage/detections/fire_20260712_224619_874682.jpg` attendu `no_fire`, prédit `fire` à 92.5640%.
- `data_set/No_Fire/Artificial_Fire (88).png` attendu `no_fire`, prédit `fire` à 88.2661%.
- `data_set/Fire/Smoke (119).png` attendu `fire`, prédit `no_fire` à 70.3296%.
- `data_set/Fire/Fire (146).png` attendu `fire`, prédit `no_fire` à 55.5307%.
- `data_set/Fire/Fire (206).png` attendu `fire`, prédit `no_fire` à 53.6102%.

## Conclusion

Le mapping réel surpasse explicitement le mapping inversé. Les captures webcam normales restent des faux positifs très confiants avec le pipeline vérifié; cela prouve un défaut de généralisation/calibration du checkpoint et non une inversion de labels.
