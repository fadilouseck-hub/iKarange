<?php
// Load .env
$envFile = __DIR__ . '/../.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    putenv("$k=" . trim($v));
}

$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'ikarange') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Connected to database.\n";

// Truncate tables in reverse dependency order
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$tables = ['primes','factures_entreprises','factures_prestataires','prises_en_charge','utilisateurs_prestataires','compagnies_assurance','access_profiles','adherents','prestataires','entreprises'];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
    echo "Truncated: $t\n";
}
// Delete non-admin users (keep id=1 which is admin)
$pdo->exec("DELETE FROM users WHERE id > 1");
echo "Cleaned extra users\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Run seed SQL
$sql = file_get_contents(__DIR__ . '/002_seed.sql');
$pdo->exec($sql);
echo "\nSeed data inserted successfully!\n\n";

// Verify counts
$tables = ['entreprises','adherents','prestataires','utilisateurs_prestataires','prises_en_charge','factures_prestataires','factures_entreprises','primes','compagnies_assurance','access_profiles','users'];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo str_pad($t, 30) . ": $c rows\n";
}

// Quick encoding check
$stmt = $pdo->query("SELECT raison_sociale FROM entreprises WHERE id = 1");
echo "\nEncoding check: " . $stmt->fetchColumn() . "\n";
echo "Done!\n";
