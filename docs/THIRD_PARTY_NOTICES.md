# Third-Party Notices

ExportListes does not vendor third-party code, fonts, images or JavaScript
libraries in this repository.

Runtime dependencies are provided by the host Dolibarr installation:

- Dolibarr core APIs and UI classes, under Dolibarr's own license terms.
- PhpSpreadsheet, only when bundled and autoloadable from the installed Dolibarr
  `includes` directory.
- Font Awesome CSS classes as exposed by the Dolibarr interface.

No external network service is called by the module, and no telemetry, analytics
or order statistics are sent to ergoCogn sàrl or any other third party.
