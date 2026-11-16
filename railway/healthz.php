<?php
// Railway health check: 200 only when osTicket's config exists and its database answers.
header('Content-Type: text/plain');
header('Cache-Control: no-store');
define('INCLUDE_DIR', __DIR__ . '/include/');
define('ROOT_PATH', '/');
$cfg = INCLUDE_DIR . 'ost-config.php';
mysqli_report(MYSQLI_REPORT_OFF);
if (!is_file($cfg)) { http_response_code(503); exit("not configured\n"); }
require $cfg;
list($host, $port) = array_pad(explode(':', DBHOST, 2), 2, 3306);
$db = @mysqli_init();
@$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (!$db || !@$db->real_connect($host, DBUSER, DBPASS, DBNAME, (int) $port)) {
    http_response_code(503); exit("database unreachable\n");
}
$r = @$db->query('SELECT 1 FROM `' . TABLE_PREFIX . 'config` LIMIT 1');
if (!$r) { http_response_code(503); exit("not installed\n"); }
echo "ok\n";
