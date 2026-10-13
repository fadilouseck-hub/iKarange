<?php
/**
 * Creates (once) a fictional member WITHOUT a contract, to review the online subscription journey in the apps
 * (test mode: placeholder formulas and prices, simulated payment — see src/api/mobile/subscription.php).
 *
 *  - a company "Souscription en ligne — test" that holds online testers (no cover of its own);
 *  - one fictional member (no real person) registered in mobile_online_subscribers.
 *
 * Additive: inserts only these rows. Idempotent: an existing login is left as is.
 *
 * Usage (on the server, from the site root):
 *   MEMBER_PASSWORD='…' php database/create_subscription_tester.php --login=Souscription
 *   php database/create_subscription_tester.php --login=Souscription --reset-password   # new random password
 *   php database/create_subscription_tester.php --login=Souscription --restart          # back to "no contract"
 * Without MEMBER_PASSWORD a random password is generated and printed once. Passwords are never stored in the
 * repository. --restart cancels that tester's test contracts so the journey can be walked again.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const ONLINE_COMPANY = 'Souscription en ligne — test';

$login = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--login=')) $login = substr($arg, 8);
}
if ($login === null || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,40}$/', $login)) {
    fwrite(STDERR, "Usage: php database/create_subscription_tester.php --login=<name> [--reset-password] [--restart]\n");
    exit(1);
}

$root = dirname(__DIR__);
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    if (getenv($k) === false) putenv("$k=" . trim($v));
}
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'ikarange') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function testerPassword(): string
{
    $chosen = getenv('MEMBER_PASSWORD');
    if ($chosen !== false && $chosen !== '') {
        if (mb_strlen($chosen) < 8) {
            fwrite(STDERR, "MEMBER_PASSWORD must be at least 8 characters.\n");
            exit(1);
        }
        return $chosen;
    }
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $chunk = '';
        for ($i = 0; $i < 4; $i++) $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $groups[] = $chunk;
    }
    return implode('-', $groups);
}

$stmt = $pdo->prepare('SELECT a.id, s.adherent_id AS online FROM adherents a LEFT JOIN mobile_online_subscribers s ON s.adherent_id = a.id WHERE a.login = ?');
$stmt->execute([$login]);
$member = $stmt->fetch();

if ($member) {
    if (!$member['online']) {
        fwrite(STDERR, "Refusing: login $login exists and is not an online-subscription tester.\n");
        exit(1);
    }
    $done = false;
    if (in_array('--restart', $argv, true)) {
        $pdo->prepare("UPDATE mobile_policies SET status = 'cancelled' WHERE adherent_id = ? AND status IN ('active', 'pending_payment')")->execute([$member['id']]);
        $pdo->prepare("UPDATE mobile_payments SET status = 'cancelled' WHERE adherent_id = ? AND status = 'pending'")->execute([$member['id']]);
        echo "Tester $login is back to \"no contract\".\n";
        $done = true;
    }
    if (in_array('--reset-password', $argv, true)) {
        $password = testerPassword();
        $pdo->prepare('UPDATE adherents SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_BCRYPT), $member['id']]);
        $pdo->prepare('UPDATE mobile_tokens SET revoked_at = NOW() WHERE adherent_id = ? AND revoked_at IS NULL')->execute([$member['id']]);
        echo getenv('MEMBER_PASSWORD') ? "Password updated for $login.\n" : "New password for $login: $password\n";
        $done = true;
    }
    if (!$done) echo "Tester $login already exists. Use --restart or --reset-password.\n";
    exit(0);
}

$orgId = (int)$pdo->query('SELECT id FROM organizations WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($orgId <= 0) {
    fwrite(STDERR, "No active organization found.\n");
    exit(1);
}

$pdo->beginTransaction();
$company = $pdo->prepare('SELECT id FROM entreprises WHERE raison_sociale = ? AND org_id = ?');
$company->execute([ONLINE_COMPANY, $orgId]);
$companyId = (int)$company->fetchColumn();
if ($companyId === 0) {
    $pdo->prepare("INSERT INTO entreprises (org_id, raison_sociale, secteur_activite, adresse, nombre_employes, taux_cotisation, taux_prise_en_charge, date_adhesion, statut)
                   VALUES (?, ?, 'Test — ne pas facturer', 'Testeurs de la souscription en ligne (application mobile)', 0, 0, 0, ?, 'actif')")
        ->execute([$orgId, ONLINE_COMPANY, date('Y-m-d')]);
    $companyId = (int)$pdo->lastInsertId();
}
$count = $pdo->prepare('SELECT COUNT(*) FROM adherents WHERE entreprise_id = ?');
$count->execute([$companyId]);
$password = testerPassword();
$pdo->prepare("INSERT INTO adherents (org_id, entreprise_id, nom, prenom, matricule, sexe, date_naissance, telephone, categorie, plafond_annuel, date_adhesion, statut, login, password_hash)
               VALUES (?, ?, 'Ndiaye', 'Mariama', ?, 'feminin', '1992-04-12', NULL, 'titulaire', 0, ?, 'actif', ?, ?)")
    ->execute([$orgId, $companyId, sprintf('SEL-%04d', (int)$count->fetchColumn() + 1), date('Y-m-d'), $login, password_hash($password, PASSWORD_BCRYPT)]);
$pdo->prepare('INSERT INTO mobile_online_subscribers (adherent_id) VALUES (?)')->execute([(int)$pdo->lastInsertId()]);
$pdo->commit();

echo "Online-subscription tester created (company #$companyId).\n";
echo "Login: $login\n";
echo getenv('MEMBER_PASSWORD') ? "Password: the one given in MEMBER_PASSWORD.\n" : "Password: $password\n";
