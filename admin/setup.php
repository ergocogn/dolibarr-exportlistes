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

if (!defined('CSRFCHECK_WITH_TOKEN')) {
    define('CSRFCHECK_WITH_TOKEN', 1);
}

// Load Dolibarr environment.
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once dol_buildpath('/exportlistes/lib/exportlistes.lib.php');

$langs->loadLangs(array('admin', 'exportlistes@exportlistes'));

if (!exportlistes_user_can_admin($user)) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action === 'set') {
    if (empty($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        accessforbidden();
    }

    $enableCsv      = GETPOSTINT('EXPORTLISTES_ENABLE_CSV');
    $enableXlsx     = GETPOSTINT('EXPORTLISTES_ENABLE_XLSX');
    $maxRows        = GETPOSTINT('EXPORTLISTES_MAX_ROWS');
    $maxPayload     = GETPOSTINT('EXPORTLISTES_MAX_PAYLOAD_BYTES');
    $csvDelimiter   = GETPOST('EXPORTLISTES_CSV_DELIMITER', 'alpha');
    $csvBom         = GETPOSTINT('EXPORTLISTES_CSV_BOM');

    $csvDelimiter = exportlistes_normalize_csv_delimiter($csvDelimiter);

    dolibarr_set_const($db, 'EXPORTLISTES_ENABLE_CSV',          $enableCsv,    'yesno',   0, '', $conf->entity);
    dolibarr_set_const($db, 'EXPORTLISTES_ENABLE_XLSX',         $enableXlsx,   'yesno',   0, '', $conf->entity);
    dolibarr_set_const($db, 'EXPORTLISTES_MAX_ROWS',            $maxRows,      'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EXPORTLISTES_MAX_PAYLOAD_BYTES',   $maxPayload,   'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EXPORTLISTES_CSV_DELIMITER',       $csvDelimiter, 'chaine',  0, '', $conf->entity);
    dolibarr_set_const($db, 'EXPORTLISTES_CSV_BOM',             $csvBom,       'yesno',   0, '', $conf->entity);

    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

llxHeader('', $langs->trans('ExportListesSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ExportListesSetup'), $linkback, 'title_setup');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('Parameters').'</th></tr>';

print '<tr><td>'.$langs->trans('EnableCSV').'</td><td>';
print '<input type="checkbox" name="EXPORTLISTES_ENABLE_CSV" value="1"'.(getDolGlobalInt('EXPORTLISTES_ENABLE_CSV', 1) ? ' checked' : '').'>';
print '</td></tr>';

print '<tr><td>'.$langs->trans('EnableXLSX').'</td><td>';
print '<input type="checkbox" name="EXPORTLISTES_ENABLE_XLSX" value="1"'.(getDolGlobalInt('EXPORTLISTES_ENABLE_XLSX', 1) ? ' checked' : '').'>';
print '</td></tr>';

print '<tr><td>'.$langs->trans('MaxRows').'</td><td>';
print '<input type="number" min="0" name="EXPORTLISTES_MAX_ROWS" value="'.((int) getDolGlobalInt('EXPORTLISTES_MAX_ROWS', 100000)).'">';
print '</td></tr>';

print '<tr><td>'.$langs->trans('MaxPayloadBytes').'</td><td>';
print '<input type="number" min="0" name="EXPORTLISTES_MAX_PAYLOAD_BYTES" value="'.((int) getDolGlobalInt('EXPORTLISTES_MAX_PAYLOAD_BYTES', 16 * 1024 * 1024)).'">';
print '</td></tr>';

print '<tr><td>'.$langs->trans('CsvDelimiter').'</td><td>';
print '<input type="text" maxlength="1" size="1" name="EXPORTLISTES_CSV_DELIMITER" value="'.dol_escape_htmltag(getDolGlobalString('EXPORTLISTES_CSV_DELIMITER', ';')).'">';
print '</td></tr>';

print '<tr><td>'.$langs->trans('CsvBom').'</td><td>';
print '<input type="checkbox" name="EXPORTLISTES_CSV_BOM" value="1"'.(getDolGlobalInt('EXPORTLISTES_CSV_BOM', 1) ? ' checked' : '').'>';
print '</td></tr>';

print '</table>';
print '<div class="center marginbottomonly margintoponly">';
print '<input class="button button-save" type="submit" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
