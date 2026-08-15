<?php

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}

require dirname(__FILE__).'/../../../main.inc.php';

llxHeader('', 'ExportListes');
print '<div class="info">Use <a href="setup.php">setup.php</a> to configure the module.</div>';
llxFooter();
$db->close();
