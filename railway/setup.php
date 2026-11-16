<?php
/*
 * Runs on every boot, before Apache starts:
 *   1. waits for MySQL,
 *   2. installs osTicket unattended on the first boot (install.php),
 *   3. writes include/ost-config.php from the service variables,
 *   4. applies pending schema upgrades after an image update (manage.php upgrade).
 * Exits non-zero on any error, so Railway restarts the container.
 */
if (PHP_SAPI !== 'cli') exit(1);
mysqli_report(MYSQLI_REPORT_OFF);

function out($m) { fwrite(STDOUT, "[osticket-setup] $m\n"); }
function fail($m) { fwrite(STDERR, "[osticket-setup] ERROR: $m\n"); exit(1); }
function env($k, $d = '') { $v = getenv($k); return ($v === false || trim($v) === '') ? $d : trim($v); }

$dbHost = env('DB_HOST');
$dbPort = (int) env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'osticket');
$dbUser = env('DB_USER');
$dbPass = env('DB_PASSWORD');
$salt   = env('OSTICKET_SECRET_SALT');
$prefix = 'ost_';

if (!$dbHost || !$dbUser || $dbPass === '') fail('DB_HOST, DB_USER and DB_PASSWORD must be set.');
if (strlen($salt) < 32) fail('OSTICKET_SECRET_SALT must be set (32 characters or more). Never change it after the first boot.');

// 1. Wait for MySQL (Railway starts both services at once).
$db = null;
for ($i = 1; $i <= 60; $i++) {
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (@$db->real_connect($dbHost, $dbUser, $dbPass, null, $dbPort)) break;
    out("waiting for MySQL at $dbHost:$dbPort ($i/60): " . mysqli_connect_error());
    $db = null;
    sleep(3);
}
if (!$db) fail('MySQL did not answer within 3 minutes.');
$db->query('CREATE DATABASE IF NOT EXISTS `' . $db->real_escape_string($dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
if (!$db->select_db($dbName)) fail("Cannot use database $dbName: " . $db->error);

$hasTables = (bool) @$db->query("SELECT 1 FROM `{$prefix}config` LIMIT 1");
$installed = false;
if ($hasTables) {
    $r = @$db->query("SELECT 1 FROM `{$prefix}config` WHERE `namespace`='core' AND `key`='schema_signature' LIMIT 1");
    $installed = $r && $r->num_rows > 0;
    if (!$installed) fail("Database $dbName has osTicket tables but no finished install (an earlier first boot was interrupted). Empty the database and redeploy.");
}
$db->close();

// 2. First boot: unattended install.
if (!$installed) {
    $adminEmail = env('ADMIN_EMAIL');
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) fail('ADMIN_EMAIL must be a valid email address for the first install.');
    if (strlen(env('ADMIN_PASSWORD')) < 8) fail('ADMIN_PASSWORD must be set (8 characters or more) for the first install.');
    out('no osTicket install found: installing ' . trim(@file_get_contents('/opt/osticket/VERSION') ?: '') . '...');
    passthru('php /opt/osticket/railway/install.php', $rc);
    if ($rc !== 0) fail("install failed (exit $rc)");
}

// 3. Config file from the service variables (rewritten on every boot, never on a volume).
$defs = array(
    'SECRET_SALT' => $salt,
    'ADMIN_EMAIL' => env('ADMIN_EMAIL', 'root@localhost'),
    'DBTYPE' => 'mysql',
    'DBHOST' => $dbHost . ':' . $dbPort,
    'DBNAME' => $dbName,
    'DBUSER' => $dbUser,
    'DBPASS' => $dbPass,
    'TABLE_PREFIX' => $prefix,
    'TRUSTED_PROXIES' => env('OSTICKET_TRUSTED_PROXIES', ''),
    'LOCAL_NETWORKS' => '127.0.0.0/24',
    'SESSION_SESSID' => 'OSTSESSID',
);
$php = "<?php\n// Written by the Railway start script on every boot from the service variables. Edits here are lost.\n"
     . "if (!defined('INCLUDE_DIR')) die('kwaheri rafiki!');\n"
     . "define('OSTINSTALLED', TRUE);\n";
foreach ($defs as $k => $v) $php .= "define('$k', " . var_export($v, true) . ");\n";
$cfg = '/var/www/html/include/ost-config.php';
if (file_put_contents("$cfg.tmp", $php) === false || !rename("$cfg.tmp", $cfg)) fail("cannot write $cfg");
chown($cfg, 'root'); chgrp($cfg, 'www-data'); chmod($cfg, 0640);
out('config written');

// 4. Apply schema upgrades after an image update. "No upgrade is pending" is the normal case.
if ($installed) {
    $o = array();
    exec('cd /var/www/html && php manage.php upgrade 2>&1', $o, $rc);
    $o = trim(implode("\n", $o));
    if (stripos($o, 'No upgrade is pending') !== false) out('schema is current');
    elseif ($rc === 0) out("upgrade: $o");
    else fail("upgrade failed: $o");
}
out('ready');
