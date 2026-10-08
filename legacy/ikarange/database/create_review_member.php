<?php
/**
 * Creates (once) a synthetic member for App Store / TestFlight review of the Assur Plus app.
 *
 *  - a clearly labelled demo company "DÉMO ASSUR PLUS — compte de test" with standard coverage rules (barèmes);
 *  - one fictional member (no real person) with portal login "review.assurplus" and a random password.
 *
 * Additive: inserts only these rows, never updates or deletes existing data. Idempotent: if the login already
 * exists nothing is created; run with --reset-password to issue a new password for that same demo member only.
 * The password is generated here and printed once to the operator's terminal; it is not stored anywhere else.
 *
 * Usage (on the server, from the site root):
 *   php database/create_review_member.php [--reset-password]                    # App Store review member
 *   php database/create_review_member.php --login=test.owner [--reset-password] # extra fictional tester
 *   MEMBER_PASSWORD='…' php database/create_review_member.php --login=Demo       # owner-chosen password (≥ 8 chars)
 * The password is never stored in the repository; a chosen one is read from the environment at run time.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Optional --login=<name> creates another fictional tester in the same demo company.
$requested = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--login=')) $requested = substr($arg, 8);
}
if ($requested !== null && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,40}$/', $requested)) {
    fwrite(STDERR, "Invalid --login (4-41 chars: letters, digits, . _ -)\n");
    exit(1);
}
define('REVIEW_LOGIN', $requested ?? 'review.assurplus');
const DEMO_COMPANY = 'DÉMO ASSUR PLUS — compte de test';

$root = dirname(__DIR__);
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    putenv("$k=" . trim($v));
}
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'ikarange') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Readable but strong: 4 groups of 4 from an unambiguous alphabet (~80 bits).
function newPassword(): string
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

$existing = $pdo->prepare('SELECT a.id, a.entreprise_id, e.raison_sociale FROM adherents a LEFT JOIN entreprises e ON e.id = a.entreprise_id WHERE a.login = ?');
$existing->execute([REVIEW_LOGIN]);
$member = $existing->fetch();

if ($member) {
    if (!in_array('--reset-password', $argv, true)) {
        echo "Review member already exists (login " . REVIEW_LOGIN . "). Use --reset-password to issue a new password.\n";
        exit(0);
    }
    if ($member['raison_sociale'] !== DEMO_COMPANY) {
        fwrite(STDERR, "Refusing: login " . REVIEW_LOGIN . " is not attached to the demo company.\n");
        exit(1);
    }
    $password = newPassword();
    $pdo->prepare('UPDATE adherents SET password_hash = ? WHERE id = ? AND login = ?')
        ->execute([password_hash($password, PASSWORD_BCRYPT), $member['id'], REVIEW_LOGIN]);
    $pdo->prepare('UPDATE mobile_tokens SET revoked_at = NOW() WHERE adherent_id = ? AND revoked_at IS NULL')->execute([$member['id']]);
    echo "New password for " . REVIEW_LOGIN . ": $password\n";
    exit(0);
}

$orgId = (int)$pdo->query("SELECT id FROM organizations WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($orgId <= 0) {
    fwrite(STDERR, "No active organization found.\n");
    exit(1);
}

$pdo->beginTransaction();
$company = $pdo->prepare('SELECT id FROM entreprises WHERE raison_sociale = ? AND org_id = ?');
$company->execute([DEMO_COMPANY, $orgId]);
$companyId = (int)$company->fetchColumn();
if ($companyId === 0) {
    $pdo->prepare("INSERT INTO entreprises (org_id, raison_sociale, secteur_activite, adresse, nombre_employes, taux_cotisation, taux_prise_en_charge, date_adhesion, statut)
                   VALUES (?, ?, 'Démonstration — ne pas facturer', 'Compte de démonstration Assur Plus', 1, 0, 80, ?, 'actif')")
        ->execute([$orgId, DEMO_COMPANY, date('Y-01-01')]);
    $companyId = (int)$pdo->lastInsertId();
    $rules = [
        ['consultation', 'Consultation', 80, null, null],
        ['pharmacie', 'Pharmacie', 80, null, null],
        ['analyse', 'Analyses / Biologie', 80, null, null],
        ['imagerie', 'Imagerie', 80, null, null],
        ['hospitalisation', 'Hospitalisation', 100, null, null],
        ['dentaire', 'Dentaire', 80, 300000, 'par_an'],
        ['optique', 'Optique', 80, 150000, 'par_2_ans'],
    ];
    $insert = $pdo->prepare('INSERT INTO baremes (org_id, entreprise_id, type_acte, libelle, taux_couverture, plafond_acte, periode_plafond) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($rules as [$type, $label, $rate, $limit, $period]) {
        $insert->execute([$orgId, $companyId, $type, $label, $rate, $limit, $period]);
    }
}

$count = $pdo->prepare('SELECT COUNT(*) FROM adherents WHERE entreprise_id = ?');
$count->execute([$companyId]);
$matricule = sprintf('DEMO-%04d', (int)$count->fetchColumn() + 1);
$password = newPassword();
$pdo->prepare("INSERT INTO adherents (org_id, entreprise_id, nom, prenom, matricule, sexe, date_naissance, telephone, categorie, plafond_annuel, date_adhesion, statut, login, password_hash)
               VALUES (?, ?, 'DEMO', ?, ?, 'feminin', '1990-01-01', NULL, 'titulaire', 1000000, ?, 'actif', ?, ?)")
    ->execute([$orgId, $companyId, REVIEW_LOGIN === 'review.assurplus' ? 'Revue' : 'Testeur', $matricule, date('Y-01-01'), REVIEW_LOGIN, password_hash($password, PASSWORD_BCRYPT)]);
$pdo->commit();

echo "Review member created in demo company #$companyId.\n";
echo "Login: " . REVIEW_LOGIN . "\n";
echo "Password: $password\n";
echo "Store it only in App Store Connect (Test Information > sign-in) and your password manager.\n";
