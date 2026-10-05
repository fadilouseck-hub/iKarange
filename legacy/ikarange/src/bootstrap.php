<?php

/**
 * Bootstrap – load env, config, DB, session, core services, helpers.
 */

// ── Env loader ──────────────────────────────────────────
function loadEnv(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        $val = trim($val);
        if (!getenv($key)) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

$basePath = dirname(__DIR__);
loadEnv($basePath . '/.env');

// ── Config ──────────────────────────────────────────────
$appConfig = require $basePath . '/config/app.php';
$dbConfig  = require $basePath . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

// ── Database ────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = $GLOBALS['dbConfig'];
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (php_sapi_name() === 'cli') {
            echo "Database connection failed: {$e->getMessage()}\nRun: php install.php\n";
            exit(1);
        }
        http_response_code(503);
        $dbName = htmlspecialchars($cfg['name']);
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>Setup</title>";
        echo "<style>body{font-family:system-ui;max-width:500px;margin:4rem auto;padding:1rem;text-align:center;}h1{font-size:1.5rem;}code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:0.9rem;}</style>";
        echo "</head><body><p style=\"font-size:3rem;\">&#x1F6E0;</p>";
        echo "<h1>Base de donn&eacute;es introuvable</h1>";
        echo "<p>Cr&eacute;ez une base MySQL <code>{$dbName}</code> puis ex&eacute;cutez :</p>";
        echo "<p><code>php install.php</code></p>";
        echo "</body></html>";
        exit;
    }
    return $pdo;
}

// ── Session ─────────────────────────────────────────────
function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $cfg = $GLOBALS['appConfig']['session'];
    session_name($cfg['name']);
    session_set_cookie_params([
        'lifetime' => $cfg['lifetime'],
        'path'     => '/',
        'secure'   => $cfg['secure'],
        'httponly'  => $cfg['httponly'],
        'samesite' => $cfg['samesite'],
    ]);
    session_start();
}

startSession();

// ── CSRF ────────────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function verifyCsrf(): bool
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrfToken(), $token);
}

// ── Auth helpers ────────────────────────────────────────
function authUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(): array
{
    $user = authUser();
    if (!$user) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Non autorisé'], 401);
        }
        header('Location: /login');
        exit;
    }
    return $user;
}

function orgId(): int
{
    // Super-admin can override org_id via session
    if (isSuperAdmin() && !empty($_SESSION['switch_org_id'])) {
        return (int)$_SESSION['switch_org_id'];
    }
    return (int)(authUser()['org_id'] ?? 0);
}

function currentOrgName(): string
{
    static $name = null;
    if ($name !== null) return $name;
    $stmt = db()->prepare('SELECT name FROM organizations WHERE id = ?');
    $stmt->execute([orgId()]);
    $name = $stmt->fetchColumn() ?: '';
    return $name;
}

function allOrgs(): array
{
    if (!isSuperAdmin()) return [];
    $stmt = db()->query('SELECT id, name FROM organizations WHERE is_active = 1 ORDER BY name');
    return $stmt->fetchAll();
}

// ── Super-admin helpers ────────────────────────────────
function isSuperAdmin(): bool
{
    return (bool)(authUser()['is_super'] ?? false);
}

function requireSuperAdmin(): array
{
    $user = requireAuth();
    if (empty($user['is_super'])) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Acces refuse'], 403);
        }
        header('Location: /');
        exit;
    }
    return $user;
}

// ── Prestataire auth helpers ───────────────────────────
function prestaUser(): ?array
{
    return $_SESSION['prestataire_user'] ?? null;
}

function requirePrestataire(): array
{
    $pu = prestaUser();
    if (!$pu) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Non autorisé'], 401);
        }
        header('Location: /tiers-payant');
        exit;
    }
    return $pu;
}

function prestaOrgId(): int
{
    return (int)(prestaUser()['org_id'] ?? 0);
}

// ── Adherent auth helpers ─────────────────────────────
function adherentUser(): ?array
{
    return $_SESSION['adherent_user'] ?? null;
}

function requireAdherent(): array
{
    $au = adherentUser();
    if (!$au) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Non autorise'], 401);
        }
        header('Location: /portail-adherent');
        exit;
    }
    return $au;
}

function adherentOrgId(): int
{
    return (int)(adherentUser()['org_id'] ?? 0);
}

// ── Request helpers ─────────────────────────────────────
function isApiRequest(): bool
{
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')
        || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
}

function jsonResponse(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonInput(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

// ── Misc ────────────────────────────────────────────────
function basePath(string $rel = ''): string
{
    return dirname(__DIR__) . ($rel ? '/' . ltrim($rel, '/') : '');
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app(): App
{
    return App::getInstance();
}

// ── Core Services ───────────────────────────────────────
require_once $basePath . '/src/core/App.php';

// ── Init App ────────────────────────────────────────────
$app = App::getInstance();

// If authenticated, load org data
$_currentUser = authUser();
if ($_currentUser) {
    $app->loadOrg((int)$_currentUser['org_id']);
}

// ── Navigation helper ───────────────────────────────────
function navItems(): array
{
    $items = [
        ['key' => 'dashboard',       'href' => '/',                          'icon' => 'bx bx-grid-alt',          'label' => 'Tableau de bord'],
        ['key' => 'tiers-payant',    'href' => '/tiers-payant',              'icon' => 'bx bx-heart',             'label' => 'Tiers payant'],
        ['key' => 'entreprises',     'href' => '/entreprises',               'icon' => 'bx bx-buildings',         'label' => 'Entreprises'],
        ['key' => 'adherents',       'href' => '/adherents',                 'icon' => 'bx bx-group',             'label' => 'Adhérents'],
        ['key' => 'prestataires',    'href' => '/prestataires',              'icon' => 'bx bx-plus-medical',      'label' => 'Prestataires'],
        ['key' => 'utilisateurs-prestataires', 'href' => '/utilisateurs-prestataires', 'icon' => 'bx bx-user-check', 'label' => 'Utilisateurs prestataires'],
        ['key' => 'pec',             'href' => '/prises-en-charge',          'icon' => 'bx bx-file',              'label' => 'Prises en charge'],
        ['key' => 'factures-prest',  'href' => '/factures-prestataires',     'icon' => 'bx bx-receipt',           'label' => 'Factures prestataires'],
        ['key' => 'factures-ent',    'href' => '/factures-entreprises',      'icon' => 'bx bx-spreadsheet',       'label' => 'Factures entreprises'],
        ['key' => 'primes',          'href' => '/primes-budget',             'icon' => 'bx bx-wallet',            'label' => 'Primes & Budget'],
        ['key' => 'statistiques',    'href' => '/statistiques',              'icon' => 'bx bx-bar-chart-alt-2',   'label' => 'Statistiques'],
        ['key' => 'rapports',        'href' => '/rapports',                  'icon' => 'bx bx-printer',           'label' => 'Rapports'],
        ['key' => 'remboursements', 'href' => '/remboursements',            'icon' => 'bx bx-money',             'label' => 'Remboursements'],
        ['key' => 'compagnies',      'href' => '/compagnies-assurance',      'icon' => 'bx bx-shield-quarter',    'label' => "Compagnies d'assurance"],
        ['key' => 'acces',           'href' => '/gestion-acces',             'icon' => 'bx bx-lock-open-alt',     'label' => "Gestion des accès"],
    ];

    if (isSuperAdmin()) {
        $items[] = ['key' => 'assureurs', 'href' => '/assureurs', 'icon' => 'bx bx-globe', 'label' => 'Assureurs'];
    }

    return $items;
}

// ── Permission helpers ──────────────────────────────────
function userPermissions(): array
{
    $user = authUser();
    if (!$user) return [];

    // Super-admin and administrateur see everything
    if (!empty($user['is_super']) || $user['role'] === 'administrateur') {
        return ['*'];
    }

    // Load from access_profiles if user has a profile_id
    if (!empty($user['profile_id'])) {
        static $cache = null;
        if ($cache !== null) return $cache;

        $stmt = db()->prepare('SELECT permissions FROM access_profiles WHERE id = ? AND org_id = ? AND is_active = 1');
        $stmt->execute([$user['profile_id'], $user['org_id']]);
        $row = $stmt->fetch();
        $cache = $row ? (json_decode($row['permissions'], true) ?: []) : [];
        return $cache;
    }

    // gestionnaire with no profile: default to dashboard only
    return ['dashboard'];
}

function hasPermission(string $module): bool
{
    $perms = userPermissions();
    return in_array('*', $perms) || in_array($module, $perms);
}

// ── Format helpers ──────────────────────────────────────
function formatMontant(int|float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' F';
}

function generateNumero(string $prefix): string
{
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// ── Bareme helpers ─────────────────────────────────────

/**
 * Look up bareme for an adherent's enterprise + act type.
 * Returns the bareme row or null.
 */
function lookupBareme(int $adherentId, int $orgId, string $typeActe, string $sousTypeActe = ''): ?array
{
    $key = $sousTypeActe ?: $typeActe;

    $stmt = db()->prepare('
        SELECT b.* FROM baremes b
        JOIN adherents a ON a.entreprise_id = b.entreprise_id AND a.org_id = b.org_id
        WHERE a.id = ? AND a.org_id = ? AND b.type_acte = ? AND b.is_active = 1
    ');
    $stmt->execute([$adherentId, $orgId, $key]);
    $bareme = $stmt->fetch();

    // Fallback to parent type if sub-type not found
    if (!$bareme && $sousTypeActe) {
        $stmt->execute([$adherentId, $orgId, $typeActe]);
        $bareme = $stmt->fetch();
    }

    return $bareme ?: null;
}

/**
 * Calculate cumulative IPM consumption for a given act type within the bareme period.
 * For par_evenement: returns 0 (ceiling applies per single PEC, not cumulative).
 */
function calculerConsommationActe(int $adherentId, int $orgId, string $typeActe, array $bareme, string $dateSoins = ''): int
{
    if (!$bareme['plafond_acte'] || $bareme['periode_plafond'] === 'par_evenement') {
        return 0;
    }

    $params = [$orgId, $adherentId, $typeActe, $typeActe];

    // Period filter
    $periodeSql = '';
    $year = $dateSoins ? date('Y', strtotime($dateSoins)) : date('Y');

    if ($bareme['periode_plafond'] === 'par_an') {
        $periodeSql = 'AND YEAR(p.date_soins) = ?';
        $params[] = $year;
    } elseif ($bareme['periode_plafond'] === 'par_2_ans') {
        $periodeSql = 'AND YEAR(p.date_soins) >= ? AND YEAR(p.date_soins) <= ?';
        $params[] = $year - 1;
        $params[] = $year;
    }

    $sql = "
        SELECT COALESCE(SUM(p.part_ipm), 0) as consomme
        FROM prises_en_charge p
        WHERE p.org_id = ?
          AND p.adherent_id = ?
          AND (p.sous_type_acte = ? OR (p.sous_type_acte IS NULL AND p.type_acte = ?))
          AND p.statut IN ('approuvee', 'facturee', 'reglee')
          $periodeSql
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetch()['consomme'];
}
