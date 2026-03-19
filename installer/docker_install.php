<?php
/**
 * Non-interactive CLI installer for Docker / Render deployments.
 * Reads all configuration from environment variables.
 * Drops any partial tables before running so failed previous attempts
 * do not leave the DB in a broken state.
 */

$pathToAutoload = realpath(__DIR__ . '/../src/vendor/autoload.php');
if (!$pathToAutoload) {
    echo "Cannot find src/vendor/autoload.php\n";
    exit(1);
}
require_once $pathToAutoload;

use OrangeHRM\Authentication\Dto\UserCredential;
use OrangeHRM\Config\Config;
use OrangeHRM\Framework\Http\Session\MemorySessionStorage;
use OrangeHRM\Framework\Http\Session\Session;
use OrangeHRM\Framework\ServiceContainer;
use OrangeHRM\Framework\Services;
use OrangeHRM\Installer\Framework\HttpKernel;
use OrangeHRM\Installer\Util\AppSetupUtility;
use OrangeHRM\Installer\Util\StateContainer;

// ── Bootstrap ────────────────────────────────────────────────────────────────
new HttpKernel('prod', false);
$sessionStorage = new MemorySessionStorage();
ServiceContainer::getContainer()->set(Services::SESSION_STORAGE, $sessionStorage);
$session = new Session($sessionStorage);
$session->start();
ServiceContainer::getContainer()->set(Services::SESSION, $session);

// ── Read env vars ─────────────────────────────────────────────────────────────
$dbHost     = getenv('ORANGEHRM_DATABASE_HOST');
$dbPort     = getenv('ORANGEHRM_DATABASE_PORT') ?: '3306';
$dbName     = getenv('ORANGEHRM_DATABASE_NAME');
$dbUser     = getenv('ORANGEHRM_DATABASE_USER');
$dbPassword = getenv('ORANGEHRM_DATABASE_PASSWORD');

$adminUsername  = getenv('ORANGEHRM_ADMIN_USERNAME')  ?: 'Admin';
$adminPassword  = getenv('ORANGEHRM_ADMIN_PASSWORD')  ?: 'Admin1234!';
$adminFirstName = getenv('ORANGEHRM_ADMIN_FIRSTNAME') ?: 'OrangeHRM';
$adminLastName  = getenv('ORANGEHRM_ADMIN_LASTNAME')  ?: 'Admin';
$adminEmail     = getenv('ORANGEHRM_ADMIN_EMAIL')     ?: 'admin@example.com';
$orgName        = getenv('ORANGEHRM_ORG_NAME')        ?: 'OrangeHRM';
$orgCountry     = getenv('ORANGEHRM_ORG_COUNTRY')     ?: 'US';

if (!$dbHost || !$dbName || !$dbUser) {
    echo "Missing required env vars (ORANGEHRM_DATABASE_HOST / NAME / USER).\n";
    exit(1);
}

// ── Drop any partial tables so broken previous attempts don't block us ────────
echo "Checking for existing tables...\n";
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
        $dbUser,
        $dbPassword,
        [
            PDO::MYSQL_ATTR_SSL_CA             => '/etc/ssl/certs/ca-certificates.crt',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($tables)) {
        echo "Found " . count($tables) . " existing table(s) — dropping for clean install...\n";
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "All tables dropped.\n";
    } else {
        echo "Database is empty — proceeding with fresh install.\n";
    }
} catch (Throwable $e) {
    echo "Warning during table cleanup: " . $e->getMessage() . "\n";
}

// ── Populate StateContainer (existing-database mode) ─────────────────────────
StateContainer::getInstance()->storeDbInfo(
    $dbHost,
    $dbPort,
    new UserCredential($dbUser, $dbPassword),
    $dbName
);
StateContainer::getInstance()->setDbType(AppSetupUtility::INSTALLATION_DB_TYPE_EXISTING);
StateContainer::getInstance()->storeInstanceData($orgName, $orgCountry, null, null);
StateContainer::getInstance()->storeAdminUserData(
    $adminFirstName,
    $adminLastName,
    $adminEmail,
    new UserCredential($adminUsername, $adminPassword),
    null
);
StateContainer::getInstance()->storeRegConsent(false);

// ── Run installation ──────────────────────────────────────────────────────────
$appSetupUtility = new AppSetupUtility();

// Aiven MySQL enforces sql_require_primary_key=ON globally.
// OrangeHRM's legacy schema has tables without PKs, so disable it for this session.
echo "Disabling sql_require_primary_key for this session (Aiven compatibility)...\n";
\OrangeHRM\Installer\Util\Connection::getConnection()
    ->executeStatement('SET SESSION sql_require_primary_key = 0');

echo "[1/3] Running migrations (this may take a few minutes over a remote DB)...\n";
$appSetupUtility->runMigrations('3.3.3', Config::PRODUCT_VERSION);
echo "[1/3] Migrations complete.\n";

echo "[2/3] Inserting system configuration and admin user...\n";
$appSetupUtility->insertSystemConfiguration();
echo "[2/3] Done.\n";

echo "[3/3] Writing Conf.php...\n";
$appSetupUtility->writeConfFile();
echo "[3/3] Done.\n";

echo "\nInstallation complete. Login: {$adminUsername} / {$adminPassword}\n";
