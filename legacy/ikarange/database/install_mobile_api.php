<?php
/**
 * Installs the mobile API on an existing I'KARANGE site. Additive and idempotent:
 *  1. creates the mobile_* tables (database/008_mobile_api.sql — CREATE TABLE IF NOT EXISTS only);
 *  2. adds the /api/mobile/v1 hook to src/router.php if missing (backup kept next to it);
 *  3. adds MOBILE_SECRET to .env if missing (random, generated here, never printed).
 * Existing tables, data and routes are not modified.
 *
 * Usage (on the server, from the site root):  php database/install_mobile_api.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (!is_file($envFile)) {
    fwrite(STDERR, "No .env found in $root\n");
    exit(1);
}
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    putenv("$k=" . trim($v));
}

// 1. Tables
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'ikarange') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$sql = file_get_contents(__DIR__ . '/008_mobile_api.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if (!preg_match('/^CREATE TABLE IF NOT EXISTS/i', $statement)) {
        fwrite(STDERR, "Refusing non-additive statement\n");
        exit(1);
    }
    $pdo->exec($statement);
}
$tables = $pdo->query("SHOW TABLES LIKE 'mobile\\_%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";

// 2. Router hook
$routerFile = $root . '/src/router.php';
$router = file_get_contents($routerFile);
if (str_contains($router, "/api/mobile/v1/")) {
    echo "Router: hook already present\n";
} else {
    $anchor = "    // Static assets\n";
    $pos = strpos($router, $anchor);
    $end = $pos === false ? false : strpos($router, "    }\n", $pos);
    if ($end === false) {
        fwrite(STDERR, "Router: anchor not found, add the hook manually (see src/router.php in the repository)\n");
        exit(1);
    }
    $end += strlen("    }\n");
    $hook = "\n    // Mobile API (Assur Plus apps): token-authenticated, self-contained router.\n"
        . "    if (str_starts_with(\$path, '/api/mobile/v1/')) {\n"
        . "        require basePath('src/api/mobile/index.php');\n"
        . "        mobileRoute(\$path, \$method);\n"
        . "    }\n";
    copy($routerFile, $routerFile . '.bak-' . date('Ymd-His'));
    file_put_contents($routerFile, substr($router, 0, $end) . $hook . substr($router, $end));
    echo "Router: hook added (backup kept)\n";
}

// 3. Secret
$env = file_get_contents($envFile);
if (preg_match('/^MOBILE_SECRET=.{32,}$/m', $env)) {
    echo "MOBILE_SECRET: already set\n";
} else {
    copy($envFile, $envFile . '.bak-' . date('Ymd-His'));
    file_put_contents($envFile, rtrim($env) . "\nMOBILE_SECRET=" . bin2hex(random_bytes(32)) . "\n");
    @chmod($envFile, 0600);
    echo "MOBILE_SECRET: generated (not displayed)\n";
}

echo "Mobile API installed.\n";
