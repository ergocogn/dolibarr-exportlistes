# ExportListes

ExportListes is a Dolibarr ERP module for exporting filtered list views to CSV and XLSX (Excel). It exports the visible rows and columns after the user has applied filters and selected the desired pagination size.

The module is intentionally lightweight: it does not create database tables, replay list SQL queries, write generated files to disk or send data to an external service.

## Features

- Export visible Dolibarr list rows to CSV.
- Export visible Dolibarr list rows to XLSX when Dolibarr's bundled PhpSpreadsheet support is available.
- Automatic CSV fallback when XLSX support is unavailable.
- Module permissions for export use and configuration.
- Admin settings for enabled formats, row limit, payload size limit, CSV delimiter and UTF-8 BOM.
- CSRF token validation, POST-only export endpoint and payload size caps.

## Requirements

- Dolibarr 23.0.3 tested on Linux.
- Dolibarr 23.0.0+ expected, but only tested versions should be declared on DoliStore.
- PHP 7.4 or higher.
- `ZipArchive` and Dolibarr's bundled PhpSpreadsheet for XLSX output. CSV output
  remains available without XLSX support.

## Download

Download the latest installable ZIP from the GitHub Releases page:

https://github.com/ergocogn/dolibarr-exportlistes/releases/latest

## Installation

Install the module in either supported external-module location:

- `htdocs/custom/exportlistes`
- `htdocs/exportlistes`

Then enable it from **Home > Setup > Modules/Applications** and configure it from the module setup page.

## Usage

1. Open a Dolibarr list page.
2. Apply the filters you want to export.
3. Use the list pagination selector to display the rows that should be included.
4. Click the export button added near the list controls.
5. Choose CSV or XLSX, depending on the enabled formats.

ExportListes exports the rows and columns currently visible in the browser. It does not export rows hidden on another pagination page.

## Configuration

The setup page lets administrators configure:

- Enabled formats: CSV, XLSX or both.
- Maximum exported row count.
- Maximum POST payload size.
- CSV delimiter.
- UTF-8 BOM for CSV files.

Non-admin users need the module export permission to use the button.

## Packaging

For DoliStore packaging, the final archive must be named `module_exportlistes-1.0.0.zip` and contain the `exportlistes/` directory directly at the ZIP root.

## Publisher Links

- Publisher display name: DoliHub - ergoCogn sàrl
- Rights holder: ergoCogn sàrl
- Dolibarr services website: https://www.dolihub.ch/
- Company website: https://www.ergocogn.ch/
- Source code and issue tracking: https://github.com/ergocogn

## Security And Data

ExportListes does not create database tables and does not write runtime files under `DOL_DOCUMENT_ROOT`. It streams exports directly to the authenticated user.

The export endpoint accepts a raw JSON payload because exported cells may contain characters such as `<`, `>` and `&`. The payload is still protected by Dolibarr
authentication, module rights, POST-only access, CSRF validation, size limits, JSON structure checks, cell normalization and CSV/XLSX formula neutralization.

## Third-Party Code

This repository does not vendor third-party libraries. It relies on Dolibarr APIs and, when available, Dolibarr's bundled PhpSpreadsheet installation. See
`docs/THIRD_PARTY_NOTICES.md`.

## License

Copyright (C) 2026 ergoCogn sàrl.

ExportListes is distributed under the GNU General Public License version 3.
See `LICENSE`.

## Support

Public support and issue tracking should be handled through the GitHub organization:

https://github.com/ergocogn

Commercial information about Dolibarr services should point to DoliHub:

https://www.dolihub.ch/
