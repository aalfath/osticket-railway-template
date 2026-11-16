<?php
// Unattended first install, using osTicket's own Installer class from the release's setup/ folder
// (kept outside the web root). Called by setup.php only when no install exists yet.
if (PHP_SAPI !== 'cli') exit(1);
mysqli_report(MYSQLI_REPORT_OFF);
function env($k, $d = '') { $v = getenv($k); return ($v === false || trim($v) === '') ? $d : trim($v); }

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$domain = env('RAILWAY_PUBLIC_DOMAIN');
$_SERVER['HTTP_HOST'] = $domain ?: 'localhost';
chdir('/var/www/html');
require '/var/www/html/bootstrap.php';

define('URL', $domain ? "https://$domain/" : 'http://localhost:' . env('PORT', '8080') . '/');
define('INC_DIR', '/opt/osticket/setup/inc/');
define('SECRET_SALT', env('OSTICKET_SECRET_SALT'));

require_once INCLUDE_DIR . 'class.setup.php';
require_once INCLUDE_DIR . 'class.validator.php';
require_once INCLUDE_DIR . 'class.passwd.php';
require_once INCLUDE_DIR . 'class.format.php';
require_once INCLUDE_DIR . 'class.misc.php';
require_once INCLUDE_DIR . 'mysqli.php';
require_once INCLUDE_DIR . 'class.i18n.php';
Internationalization::bootstrap();
require_once INC_DIR . 'class.installer.php';

$adminEmail = env('ADMIN_EMAIL');
list(, $adminDomain) = explode('@', $adminEmail, 2);
$systemEmail = env('HELPDESK_EMAIL', "support@$adminDomain");
if (!strcasecmp($systemEmail, $adminEmail)) $systemEmail = "helpdesk@$adminDomain";

// The installer rewrites this throwaway copy; setup.php writes the real config from the variables.
$tmpConfig = tempnam(sys_get_temp_dir(), 'ostcfg');
copy(INC_DIR . 'ost-sampleconfig.php', $tmpConfig);

$installer = new Installer($tmpConfig);
$ok = $installer->install(array(
    'name'        => env('HELPDESK_NAME', 'Support Center'),
    'email'       => $systemEmail,
    'fname'       => env('ADMIN_FIRSTNAME', 'Helpdesk'),
    'lname'       => env('ADMIN_LASTNAME', 'Admin'),
    'admin_email' => $adminEmail,
    'username'    => env('ADMIN_USERNAME', 'ostadmin'),
    'passwd'      => env('ADMIN_PASSWORD'),
    'passwd2'     => env('ADMIN_PASSWORD'),
    'prefix'      => 'ost_',
    'dbhost'      => env('DB_HOST') . ':' . env('DB_PORT', '3306'),
    'dbname'      => env('DB_NAME', 'osticket'),
    'dbuser'      => env('DB_USER'),
    'dbpass'      => env('DB_PASSWORD'),
    'lang_id'     => 'en_US',
    'timezone'    => env('TZ', 'UTC'),
));
@unlink($tmpConfig);
if (!$ok) {
    fwrite(STDERR, '[osticket-setup] installer errors: ' . json_encode($installer->getErrors()) . "\n");
    exit(1);
}
fwrite(STDOUT, "[osticket-setup] installed: admin user " . env('ADMIN_USERNAME', 'ostadmin') . ", helpdesk email $systemEmail, URL " . URL . "\n");
exit(0);
