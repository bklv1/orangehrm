<?php
/**
 * Non-interactive CLI installer for Docker / Render deployments.
 * Reads all configuration from environment variables.
 *
 * Required env vars:
 *   ORANGEHRM_DATABASE_HOST, ORANGEHRM_DATABASE_PORT,
 *   ORANGEHRM_DATABASE_NAME, ORANGEHRM_DATABASE_USER,
 *   ORANGEHRM_DATABASE_PASSWORD
 *
 * Optional env vars (defaults shown):
 *   ORANGEHRM_ADMIN_USERNAME   (Admin)
 *   ORANGEHRM_ADMIN_PASSWORD   (Admin1234!)
 *   ORANGEHRM_ADMIN_FIRSTNAME  (OrangeHRM)
 *   ORANGEHRM_ADMIN_LASTNAME   (Admin)
 *   ORANGEHRM_ADMIN_EMAIL      (admin@example.com)
 *   ORANGEHRM_ORG_NAME         (OrangeHRM)
 *   ORANGEHRM_ORG_COUNTRY      (US)
 */

$pathToAutoload = realpath(__DIR__ . '/../src/vendor/autoload.php');
if (!$pathToAutoload) {
    echo "Cannot find composer dependencies (src/vendor/autoload.php).\n";
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

if (Config::isInstalled()) {
    echo "Already installed — nothing to do.\n";
    exit(0);
}

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

echo "[1/4] Running migrations (this may take a few minutes)...\n";
$appSetupUtility->runMigrations('3.3.3', Config::PRODUCT_VERSION);
echo "[1/4] Migrations complete.\n";

echo "[2/4] Inserting system configuration...\n";
$appSetupUtility->insertSystemConfiguration();
echo "[2/4] Done.\n";

echo "[3/4] Creating OrangeHRM DB user (skipped for existing-DB mode)...\n";
try {
    $appSetupUtility->createDBUser();
    echo "[3/4] Done.\n";
} catch (Throwable $e) {
    echo "[3/4] Skipped: " . $e->getMessage() . "\n";
}

echo "[4/4] Writing configuration file...\n";
$appSetupUtility->writeConfFile();
echo "[4/4] Done.\n";

echo "\nInstallation complete! Admin login: {$adminUsername} / {$adminPassword}\n";
