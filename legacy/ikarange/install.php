<?php

/**
 * I'KARANGE - Gestion IPM & Mutuelle – Installateur
 *
 * Lance une seule fois pour initialiser la base de donnees et preparer l'environnement.
 *
 * Utilisation :
 *   php install.php                 (CLI interactif)
 *   Ouvrir /install dans le navigateur (web, supprimer apres usage)
 */

$basePath = __DIR__;
$isCli = php_sapi_name() === 'cli';

function out(string $msg, bool $isCli): void
{
    echo $isCli ? strip_tags("$msg") . "\n" : "<p>$msg</p>";
}

if (!$isCli) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>I\'KARANGE Installateur</title>';
    echo '<style>body{font-family:system-ui;max-width:700px;margin:2rem auto;padding:1rem;background:#f8fafa;color:#1a3a3a;} p{margin:0.5rem 0;} .ok{color:#16a34a;} .err{color:#dc2626;} .warn{color:#d97706;} h1{color:#1a3a3a;border-bottom:3px solid #2dd4a8;padding-bottom:0.5rem;} h2{margin-top:1.5rem;color:#1a3a3a;} pre{background:#f0fdf4;padding:1rem;border-radius:6px;overflow-x:auto;font-size:0.85rem;line-height:1.5;border:1px solid #2dd4a8;} ol{line-height:1.8;} code{background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:0.9rem;}</style>';
    echo '</head><body><h1>I\'KARANGE - Installateur</h1>';
}

// ── 1. Verification de la version PHP ──
out("Version PHP : " . PHP_VERSION, $isCli);
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    out('<span class="err">PHP 8.1+ est requis. Version actuelle : ' . PHP_VERSION . '</span>', $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
out('<span class="ok">Version PHP OK</span>', $isCli);

// ── 2. Verification des extensions requises ──
out($isCli ? "\n=== Extensions PHP ===" : "\n<h2>Extensions PHP</h2>", $isCli);

$required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'];
$missingExt = false;
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        out("<span class='ok'>Extension $ext : OK</span>", $isCli);
    } else {
        out("<span class='err'>Extension $ext : MANQUANTE</span>", $isCli);
        $missingExt = true;
    }
}

// GD is optional but needed for icon generation
if (extension_loaded('gd')) {
    out("<span class='ok'>Extension gd : OK (generation d'icones)</span>", $isCli);
} else {
    out("<span class='warn'>Extension gd : ABSENTE (les icones PWA devront etre placees manuellement)</span>", $isCli);
}

if ($missingExt) {
    out('<span class="err">Des extensions requises sont manquantes. Installez-les avant de continuer.</span>', $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}

// ── 3. Creation du fichier .env si absent ──
out($isCli ? "\n=== Configuration ===" : "\n<h2>Configuration</h2>", $isCli);

$envFile = "$basePath/.env";
if (!file_exists($envFile)) {
    if (file_exists("$basePath/.env.example")) {
        copy("$basePath/.env.example", $envFile);
        out('<span class="warn">.env cree a partir de .env.example – modifiez-le avec vos identifiants de base de donnees</span>', $isCli);
    } else {
        out('<span class="err">.env.example introuvable. Creez un fichier .env manuellement.</span>', $isCli);
    }
} else {
    out('<span class="ok">.env existe</span>', $isCli);
}

// ── 4. Creation des repertoires de stockage ──
out($isCli ? "\n=== Repertoires ===" : "\n<h2>Repertoires</h2>", $isCli);

$dirs = ['storage/uploads', 'storage/logs'];
foreach ($dirs as $d) {
    $path = "$basePath/$d";
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            out("<span class='ok'>$d : cree</span>", $isCli);
        } else {
            out("<span class='err'>$d : impossible de creer le repertoire</span>", $isCli);
        }
    } else {
        out("<span class='ok'>$d : existe</span>", $isCli);
    }
    if (is_writable($path)) {
        out("<span class='ok'>$d : accessible en ecriture</span>", $isCli);
    } else {
        out("<span class='err'>$d : NON accessible en ecriture</span>", $isCli);
    }
}

// ── 5. Connexion MySQL et migrations ──
out($isCli ? "\n=== Base de donnees ===" : "\n<h2>Base de donnees</h2>", $isCli);

// Charger le .env
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        putenv("$key=" . trim($val));
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'ikarange';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$pdo = null;

try {
    // Connexion sans nom de base pour la creer si necessaire
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("<span class='ok'>Base de donnees '$dbName' prete</span>", $isCli);

    // Reconnexion avec le nom de la base
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Executer les fichiers de migration dans l'ordre
    out($isCli ? "\n=== Migrations ===" : "\n<h2>Migrations</h2>", $isCli);

    $sqlFiles = glob("$basePath/database/0*.sql");
    sort($sqlFiles);

    if (empty($sqlFiles)) {
        out('<span class="warn">Aucun fichier de migration trouve dans database/ (fichiers 0*.sql)</span>', $isCli);
    } else {
        foreach ($sqlFiles as $sqlFile) {
            $filename = basename($sqlFile);
            try {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
                out("<span class='ok'>Migration appliquee : $filename</span>", $isCli);
            } catch (PDOException $e) {
                out("<span class='err'>Erreur migration $filename : " . htmlspecialchars($e->getMessage()) . "</span>", $isCli);
            }
        }
    }
} catch (PDOException $e) {
    out('<span class="err">Erreur de connexion a la base de donnees : ' . htmlspecialchars($e->getMessage()) . '</span>', $isCli);
    out('<span class="warn">Modifiez le fichier .env avec vos identifiants corrects puis relancez ce script.</span>', $isCli);
}

// ── 6. Generation des icones PWA ──
out($isCli ? "\n=== Icones PWA ===" : "\n<h2>Icones PWA</h2>", $isCli);

$iconDir = "$basePath/public/icons";
if (!is_dir($iconDir)) mkdir($iconDir, 0755, true);

if (extension_loaded('gd')) {
    foreach ([192, 512] as $size) {
        $iconPath = "$iconDir/icon-$size.png";
        $img = imagecreatetruecolor($size, $size);

        // Couleur de fond vert menthe #2dd4a8 => R=45, G=212, B=168
        $mintGreen = imagecolorallocate($img, 45, 212, 168);
        $white = imagecolorallocate($img, 255, 255, 255);

        // Remplir le fond
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $mintGreen);

        // Dessiner le texte "IK" au centre
        // Utiliser imagestring avec la plus grande police integree (police 5)
        $text = 'IK';
        $fontWidth = imagefontwidth(5);
        $fontHeight = imagefontheight(5);
        $textWidth = $fontWidth * strlen($text);

        // Calculer l'echelle pour remplir ~50% de l'icone
        $scale = (int)($size * 0.04);
        if ($scale < 1) $scale = 1;

        // Pour les grandes tailles, dessiner des caracteres agrandis manuellement
        // en utilisant des rectangles pour simuler des lettres epaisses
        $letterHeight = (int)($size * 0.45);
        $letterWidth = (int)($size * 0.18);
        $strokeWidth = (int)($size * 0.05);
        if ($strokeWidth < 2) $strokeWidth = 2;
        $spacing = (int)($size * 0.06);
        $totalWidth = $letterWidth * 2 + $spacing;
        $startX = (int)(($size - $totalWidth) / 2);
        $startY = (int)(($size - $letterHeight) / 2);

        // Lettre "I" – une barre verticale
        $ix = $startX;
        $iy = $startY;
        imagefilledrectangle($img, $ix, $iy, $ix + $strokeWidth - 1, $iy + $letterHeight - 1, $white);
        // Serif haut
        imagefilledrectangle($img, $ix - (int)($strokeWidth * 0.8), $iy, $ix + $strokeWidth + (int)($strokeWidth * 0.8) - 1, $iy + $strokeWidth - 1, $white);
        // Serif bas
        imagefilledrectangle($img, $ix - (int)($strokeWidth * 0.8), $iy + $letterHeight - $strokeWidth, $ix + $strokeWidth + (int)($strokeWidth * 0.8) - 1, $iy + $letterHeight - 1, $white);

        // Lettre "K"
        $kx = $startX + $letterWidth + $spacing;
        $ky = $startY;
        // Barre verticale gauche
        imagefilledrectangle($img, $kx, $ky, $kx + $strokeWidth - 1, $ky + $letterHeight - 1, $white);
        // Diagonale superieure du K (haut-droite)
        $midY = $ky + (int)($letterHeight / 2);
        $armLength = (int)($letterWidth * 0.9);
        for ($i = 0; $i < $strokeWidth; $i++) {
            imageline($img, $kx + $strokeWidth, $midY + $i, $kx + $strokeWidth + $armLength, $ky + $i, $white);
        }
        // Epaissir la diagonale superieure
        for ($i = 0; $i < $strokeWidth; $i++) {
            imageline($img, $kx + $strokeWidth + $i, $midY, $kx + $strokeWidth + $armLength + $i, $ky, $white);
        }
        // Diagonale inferieure du K (bas-droite)
        for ($i = 0; $i < $strokeWidth; $i++) {
            imageline($img, $kx + $strokeWidth, $midY + $i, $kx + $strokeWidth + $armLength, $ky + $letterHeight - 1 + $i, $white);
        }
        // Epaissir la diagonale inferieure
        for ($i = 0; $i < $strokeWidth; $i++) {
            imageline($img, $kx + $strokeWidth + $i, $midY, $kx + $strokeWidth + $armLength + $i, $ky + $letterHeight - 1, $white);
        }

        imagepng($img, $iconPath);
        imagedestroy($img);
        out("<span class='ok'>icon-$size.png genere (fond #2dd4a8, texte \"IK\" blanc)</span>", $isCli);
    }
} else {
    out('<span class="warn">Extension GD non disponible – placez icon-192.png et icon-512.png manuellement dans public/icons/</span>', $isCli);
    foreach ([192, 512] as $size) {
        if (!file_exists("$iconDir/icon-$size.png")) {
            file_put_contents("$iconDir/icon-$size.png", '');
        }
    }
}

// ── 7. Creation de l'administrateur et de l'organisation par defaut ──
out($isCli ? "\n=== Compte administrateur ===" : "\n<h2>Compte administrateur</h2>", $isCli);

if ($pdo) {
    try {
        // Verifier si la table organizations existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'organizations'")->rowCount();
        if ($tableCheck === 0) {
            out('<span class="warn">Table "organizations" introuvable. Les migrations doivent d\'abord creer les tables.</span>', $isCli);
        } else {
            // Creer l'organisation par defaut si aucune n'existe
            $orgCount = (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
            $orgId = 1;
            if ($orgCount === 0) {
                $pdo->exec("INSERT INTO organizations (name) VALUES (\"I'KARANGE\")");
                $orgId = (int)$pdo->lastInsertId();
                out("<span class='ok'>Organisation par defaut \"I'KARANGE\" creee (ID: $orgId)</span>", $isCli);
            } else {
                $orgId = (int)$pdo->query("SELECT id FROM organizations ORDER BY id LIMIT 1")->fetchColumn();
                out("<span class='ok'>Organisation existante trouvee (ID: $orgId)</span>", $isCli);
            }

            // Verifier si la table users existe
            $usersCheck = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount();
            if ($usersCheck === 0) {
                out('<span class="warn">Table "users" introuvable. Les migrations doivent d\'abord creer les tables.</span>', $isCli);
            } else {
                // Creer l'administrateur par defaut si aucun utilisateur n'existe
                $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                if ($userCount === 0) {
                    $passwordHash = password_hash('admin123', PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (login, password_hash, full_name, role, org_id, is_super) VALUES (?, ?, ?, ?, ?, 1)"
                    );
                    $stmt->execute(['admin', $passwordHash, 'Administrateur', 'administrateur', $orgId]);
                    out("<span class='ok'>Administrateur par defaut cree :</span>", $isCli);
                    out("  Identifiant : <strong>admin</strong>", $isCli);
                    out("  Mot de passe : <strong>admin123</strong>", $isCli);
                    out('<span class="warn">IMPORTANT : Changez le mot de passe administrateur apres la premiere connexion !</span>', $isCli);
                } else {
                    out("<span class='ok'>Des utilisateurs existent deja ($userCount). Aucun compte cree.</span>", $isCli);
                }
            }
        }
    } catch (PDOException $e) {
        out('<span class="err">Erreur lors de la creation du compte admin : ' . htmlspecialchars($e->getMessage()) . '</span>', $isCli);
        out('<span class="warn">Assurez-vous que les migrations ont ete executees correctement.</span>', $isCli);
    }
} else {
    out('<span class="warn">Connexion a la base de donnees indisponible. Le compte administrateur n\'a pas pu etre cree.</span>', $isCli);
}

// ── 8. Installation terminee – instructions de deploiement ──
out($isCli ? "\n=== Installation terminee ===" : "\n<h2>Installation terminee</h2>", $isCli);
out('<span class="ok">I\'KARANGE - Gestion IPM & Mutuelle est pret.</span>', $isCli);
out('<span class="warn">Supprimez ce fichier (install.php) en production.</span>', $isCli);

if ($isCli) {
    echo "\n";
    echo "========================================\n";
    echo "  INSTRUCTIONS DE DEPLOIEMENT\n";
    echo "========================================\n";
    echo "\n";
    echo "1. Transférez les fichiers via FTP/SSH\n";
    echo "2. Pointez le document root vers public/\n";
    echo "3. Assurez-vous que mod_rewrite est activé\n";
    echo "4. Créez la base MySQL, mettez à jour .env\n";
    echo "5. Exécutez : php install.php\n";
    echo "6. Connectez-vous avec admin / admin123\n";
    echo "7. Changez le mot de passe administrateur\n";
    echo "8. Supprimez install.php du serveur\n";
    echo "\n";
    echo "========================================\n";
} else {
    echo '<h2>Instructions de deploiement</h2>';
    echo '<ol>';
    echo '<li><strong>Transf&eacute;rez les fichiers via FTP/SSH</strong></li>';
    echo '<li><strong>Pointez le document root vers <code>public/</code></strong></li>';
    echo '<li><strong>Assurez-vous que <code>mod_rewrite</code> est activ&eacute;</strong></li>';
    echo '<li><strong>Cr&eacute;ez la base MySQL, mettez &agrave; jour <code>.env</code></strong></li>';
    echo '<li><strong>Ex&eacute;cutez : <code>php install.php</code></strong></li>';
    echo '<li><strong>Connectez-vous avec <code>admin</code> / <code>admin123</code></strong></li>';
    echo '<li><strong>Changez le mot de passe administrateur imm&eacute;diatement</strong></li>';
    echo '<li><strong>Supprimez <code>install.php</code> du serveur de production</strong></li>';
    echo '</ol>';
    echo '<pre>';
    echo "# Commandes rapides :\n";
    echo "composer install          # Si composer.json existe\n";
    echo "php install.php           # Initialiser la base de donn&eacute;es\n";
    echo "chmod -R 755 storage/     # Permissions de stockage\n";
    echo '</pre>';
}

if (!$isCli) echo '</body></html>';
