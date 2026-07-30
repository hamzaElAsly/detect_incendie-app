# Validation du dataset IA

Date : 29 juillet 2026

Dataset : `../Python/fire_factory_dataset`, classes `fire=0`, `smoke=1`.

L'artefact de validation existant daté du 14 juillet 2026 indique :

- 20 309 images et 20 309 labels;
- 14 411 train, 2 812 validation, 3 086 test;
- 21 740 boxes fire et 13 146 boxes smoke, soit 34 886 annotations;
- 0 annotation invalide restante;
- 0 fichier non apparié;
- 0 fuite critique de groupes;
- 259 assets exclus avec traçabilité;
- 331 annotations originales invalides exclues/corrigées;
- 373 paires quasi similaires regroupées.

Erreurs critiques connues dans cet artefact : aucune.

Avertissements :

- aucun hard negative explicitement documenté;
- aucune négative dans le split validation;
- diversité industrielle et licence à confirmer humainement;
- le validateur complet courant a dépassé 300 secondes sans verdict.

Conclusion : dataset préparé et précédemment validé, mais revalidation complète
obligatoire avant l'entraînement Phase 4.
