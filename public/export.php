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

// Bootstrap constants must be defined before main.inc.php.
define('NOTOKENRENEWAL', 1); // CSRF token is validated manually below.
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

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

require_once dol_buildpath('/exportlistes/lib/exportlistes.lib.php');
require_once dol_buildpath('/exportlistes/class/exportservice.class.php');

$langs->loadLangs(array('exportlistes@exportlistes', 'main'));

// 1. Module must be enabled.
if (!exportlistes_is_module_enabled()) {
    accessforbidden();
}

// 2. User must be authenticated.
if (empty($user->id)) {
    accessforbidden();
}

// 3. User must have export rights (admin or exportlistes->use).
if (!exportlistes_user_can_export($user)) {
    accessforbidden();
}

// 4. Only POST requests are accepted (the dataset is always submitted via form POST).
if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// 5. Reject oversized payloads (defensive cap before any parsing).
$rawLen = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
$maxBytes = (int) getDolGlobalInt('EXPORTLISTES_MAX_PAYLOAD_BYTES', 16 * 1024 * 1024); // 16 MB default.
if ($maxBytes > 0 && $rawLen > $maxBytes) {
    http_response_code(413);
    exit;
}

// 6. CSRF: the submitted token must match a valid Dolibarr session token.
$submittedToken = GETPOST('token', 'alpha');
if (!exportlistes_check_csrf_token($submittedToken)) {
    http_response_code(403);
    exit;
}

// 7. Format selection.
$format = GETPOST('format', 'aZ09');
if ($format !== 'csv' && $format !== 'xlsx') {
    $format = 'csv';
}
if ($format === 'csv' && !getDolGlobalInt('EXPORTLISTES_ENABLE_CSV', 1)) {
    accessforbidden();
}
if ($format === 'xlsx' && !getDolGlobalInt('EXPORTLISTES_ENABLE_XLSX', 1)) {
    accessforbidden();
}

// 8. Inputs.
$contextpage = GETPOST('contextpage', 'aZ09');
// Raw JSON payload: GETPOST('payload', 'none') can alter large JSON form values on Dolibarr 23.
// Use PHP's raw POST filter after POST/auth/rights/CSRF checks, then validate strictly in the service.
$payload = filter_has_var(INPUT_POST, 'payload') ? (string) filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW) : '';

// 9. Allow generous time for XLSX writing on big lists, but keep the cap finite.
@set_time_limit(120);

$service = new ExportListesService($db, $user);
$service->run($payload, $format, $contextpage);

$db->close();
