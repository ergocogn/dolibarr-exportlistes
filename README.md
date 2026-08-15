# ExportListes

Module Dolibarr (custom) pour exporter les listes filtrees en CSV/XLSX sans etre limite a la pagination de l'affichage.

## Fonctionnalites MVP
- Bouton Export injecte via hooks sur les pages de liste.
- Conserve les filtres actifs de la liste.
- Ignore la pagination pour exporter toute la selection.
- Formats: CSV et XLSX (Excel 2007).
- Configuration admin: formats actifs, limite de lignes, TTL token, separateur CSV.

## Installation
1. Copier le dossier exportlistes dans custom/.
2. Activer le module dans Configuration > Modules.
3. Ouvrir la page de configuration du module.

## Notes
- Le format XLSX utilise la mecanique native Dolibarr (export Excel 2007 / PhpSpreadsheet embarque dans includes).
- Si le support XLSX n'est pas disponible sur le serveur, fallback automatique en CSV.
- Le module tente d'utiliser la requete SQL de la liste capturee par hook pour garantir les chiffres exacts de la selection filtree.
