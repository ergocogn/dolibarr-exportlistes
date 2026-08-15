<?php
/* Copyright (C) 2026       ergoCogn sàrl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once dol_buildpath('/exportlistes/lib/exportlistes.lib.php');

/**
 * Export service: receives a JSON dataset { headers: [...], rows: [[...]] }
 * scraped from the Dolibarr list page DOM and streams it as CSV or XLSX.
 *
 * No SQL access, no business logic — purely format conversion + download.
 */
class ExportListesService
{
    /** @var DoliDB */
    private $db;

    /** @var User */
    private $user;

    /**
     * @param DoliDB $db
     * @param User   $user
     */
    public function __construct($db, $user)
    {
        $this->db   = $db;
        $this->user = $user;
    }

    /**
     * Validate request, decode payload, output file.
     *
     * @param string $payloadJson Raw JSON string with keys headers (string[]) and rows (string[][]).
     * @param string $format      'csv' or 'xlsx'.
     * @param string $contextpage Optional, used as filename prefix.
     * @return void
     */
    public function run($payloadJson, $format, $contextpage)
    {
        global $langs;

        if (!exportlistes_user_can_export($this->user)) {
            accessforbidden();
        }

        // Defensive size cap on the JSON string itself.
        $maxBytes = (int) getDolGlobalInt('EXPORTLISTES_MAX_PAYLOAD_BYTES', 16 * 1024 * 1024);
        if ($maxBytes > 0 && strlen((string) $payloadJson) > $maxBytes) {
            http_response_code(413);
            return;
        }

        $data = json_decode((string) $payloadJson, true);
        if (!is_array($data) || !isset($data['headers']) || !isset($data['rows'])
            || !is_array($data['headers']) || !is_array($data['rows'])) {
            http_response_code(400);
            print dol_escape_htmltag($langs->trans('Error')).': payload invalide';
            return;
        }

        // Cap header count and individual cell length.
        $maxCols = 500;
        if (count($data['headers']) > $maxCols) {
            http_response_code(413);
            return;
        }

        $maxCellLen = 32 * 1024; // 32 KB per cell.
        $headers    = array();
        foreach ($data['headers'] as $h) {
            $headers[] = $this->sanitizeStr((string) $h, $maxCellLen);
        }

        $maxRows = (int) getDolGlobalInt('EXPORTLISTES_MAX_ROWS', 100000);
        if ($maxRows > 0 && count($data['rows']) > $maxRows) {
            http_response_code(413);
            print dol_escape_htmltag($langs->trans('Error')).': limite de lignes dépassée';
            return;
        }

        $rows     = array();
        $colCount = count($headers);
        foreach ($data['rows'] as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }
            $row = array();
            for ($i = 0; $i < $colCount; $i++) {
                $val   = isset($rawRow[$i]) ? (string) $rawRow[$i] : '';
                $row[] = $this->sanitizeStr($val, $maxCellLen);
            }
            $rows[] = $row;
        }

        $safeCtx = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $contextpage);
        if ($safeCtx === '' || $safeCtx === false) {
            $safeCtx = 'list';
        }
        $safeCtx = substr($safeCtx, 0, 40);
        $filenameBase = $safeCtx.'_'.dol_print_date(dol_now(), '%Y%m%d_%H%M%S');

        if ($format === 'xlsx') {
            if ($this->outputXlsx($headers, $rows, $filenameBase.'.xlsx')) {
                return;
            }
            // Fallback to CSV if XLSX support is unavailable on this Dolibarr install.
        }

        $this->outputCsv($headers, $rows, $filenameBase.'.csv');
    }

    /**
     * Normalize string for safe inclusion in CSV/XLSX.
     *
     * Defenses:
     *  - Decode accidental HTML entities back to UTF-8.
     *  - Force UTF-8 if browser somehow sent ISO-8859-1.
     *  - Strip null bytes (break CSV/XLSX parsers).
     *  - Strip control chars (except tab \t = U+0009 and newline \n).
     *  - Truncate at $maxLen to defeat memory exhaustion with crafted payloads.
     *  - Neutralize CSV/XLSX formula injection (= + - @ TAB CR at start).
     *
     * @param string $s
     * @param int    $maxLen Maximum byte length kept (default 32 KB).
     * @return string
     */
    private function sanitizeStr($s, $maxLen = 32768)
    {
        $s = (string) $s;

        if ($s === '') {
            return '';
        }

        // Decode accidental HTML entities (textContent normally already decodes).
        if (strpos($s, '&') !== false) {
            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Force UTF-8 when mbstring is available.
        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding') && !mb_check_encoding($s, 'UTF-8')) {
            $converted = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
            if ($converted !== false) {
                $s = $converted;
            }
        }

        // Strip null bytes and dangerous control characters (keep \t and \n).
        $s = str_replace("\0", '', $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        if ($s === null) {
            // Regex failure on non-UTF-8 bytes: return safe empty.
            return '';
        }

        // Length cap.
        if ($maxLen > 0 && strlen($s) > $maxLen) {
            if (function_exists('mb_strcut')) {
                $s = mb_strcut($s, 0, $maxLen, 'UTF-8');
            } else {
                $s = substr($s, 0, $maxLen);
            }
        }

        // CSV/XLSX formula injection protection: prefix a single quote when the
        // cell starts with =, +, -, @, TAB or CR (Excel/LibreOffice formulas).
        if ($s !== '' && in_array($s[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            $s = "'".$s;
        }

        return $s;
    }

    /**
     * Stream CSV download.
     *
     * @param array<int,string>             $headers
     * @param array<int,array<int,string>>  $rows
     * @param string                        $filename
     * @return void
     */
    private function outputCsv($headers, $rows, $filename)
    {
        $delimiter = exportlistes_normalize_csv_delimiter(getDolGlobalString('EXPORTLISTES_CSV_DELIMITER'));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Sanitize filename for the Content-Disposition header (RFC 6266 safe set).
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        if ($safeName === null || $safeName === '') {
            $safeName = 'export.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$safeName.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (!$out) {
            return;
        }

        if (getDolGlobalInt('EXPORTLISTES_CSV_BOM', 1)) {
            fwrite($out, "\xEF\xBB\xBF");
        }

        if (!empty($headers)) {
            fputcsv($out, $headers, $delimiter);
        }
        foreach ($rows as $row) {
            fputcsv($out, $row, $delimiter);
        }

        fclose($out);
    }

    /**
     * Stream XLSX download via PhpSpreadsheet (bundled with Dolibarr).
     *
     * @param array<int,string>             $headers
     * @param array<int,array<int,string>>  $rows
     * @param string                        $filename
     * @return bool True on success, false if XLSX support is unavailable.
     */
    private function outputXlsx($headers, $rows, $filename)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $autoload = DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
            $psrAuto  = DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            if (file_exists($psrAuto)) {
                require_once $psrAuto;
            }
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            return false;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $sheetData = array();
        if (!empty($headers)) {
            $sheetData[] = $headers;
        }
        foreach ($rows as $row) {
            $sheetData[] = $row;
        }
        if (!empty($sheetData)) {
            $sheet->fromArray($sheetData, null, 'A1');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        if ($safeName === null || $safeName === '') {
            $safeName = 'export.xlsx';
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$safeName.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        return true;
    }
}
