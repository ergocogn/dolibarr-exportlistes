<?php

// Bootstrap constants must be defined before main.inc.php.
define('NOTOKENRENEWAL', 1); // CSRF token is validated manually below.
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require dirname(__FILE__).'/../../../main.inc.php';

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
// Raw JSON payload: do NOT use GETPOST sanitizers (they would mangle <, >, & in cells).
$payload = isset($_POST['payload']) ? (string) $_POST['payload'] : '';

// 9. Allow generous time for XLSX writing on big lists, but keep the cap finite.
@set_time_limit(120);

$service = new ExportListesService($db, $user);
$service->run($payload, $format, $contextpage);

$db->close();
