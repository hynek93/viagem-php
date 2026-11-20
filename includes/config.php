<?php

define('DEBUG', false);

define('ROOT', dirname(dirname(__FILE__)));

// Hlasime vse (vcetne deprecations pred PHP 9), ale jen do logu.
// Uzivatelum se chyby zobrazuji pouze v DEBUG rezimu.
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/logs/php_error.log');

date_default_timezone_set('Europe/Prague');
setlocale(LC_ALL, 'cs_CZ.UTF-8', 'cs_CZ', 'czech');

define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') : 'db');
define('DB_NAME', 'legacy_crm');
define('DB_USER', 'crm');
define('DB_PASS', 'crm');

define('APP_NAME', 'Evidence vlastníků');
define('APP_VERSION', '2.7.3');
define('APP_USER', 'operator.a');

define('LIST_LIMIT', 50);
define('EXPORT_LIMIT', 20000);

define('LOG_DIR', ROOT . '/logs');
define('REPORT_DIR', ROOT . '/reports');

define('REPORT_PING_URL', 'http://reporting.invalid/api/ping');
define('REPORT_UPLOAD_URL', 'http://reporting.invalid/api/upload');

require_once ROOT . '/includes/loader.php';
