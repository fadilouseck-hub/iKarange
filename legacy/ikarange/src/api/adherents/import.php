<?php

/**
 * POST /api/adherents/import
 * Import adherents from a CSV or XLSX file.
 *
 * Expected CSV columns (semicolon-separated, UTF-8):
 *   Nom; Prenom; Entreprise; Sexe; Date de naissance; Telephone;
 *   Categorie; Plafond annuel; Date adhesion; Statut
 *
 * Entreprise is matched by raison_sociale.
 * Matricule is auto-generated.
 */

$user = requireAuth();
$oid  = orgId();

if (requestMethod() !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

// ── Check file upload ──
if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Aucun fichier fourni'], 422);
}

$tmpFile  = $_FILES['file']['tmp_name'];
$origName = $_FILES['file']['name'] ?? 'file';
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

// ── Parse rows based on file type ──
$rows = [];

if ($ext === 'csv' || $ext === 'txt') {
    $rows = parseCsv($tmpFile);
} elseif ($ext === 'xlsx') {
    $rows = parseXlsx($tmpFile);
} else {
    jsonResponse(['error' => 'Format non supporté. Utilisez CSV ou XLSX.'], 422);
}

if (empty($rows)) {
    jsonResponse(['error' => 'Fichier vide ou illisible'], 422);
}

// ── Load entreprises lookup ──
$stmt = db()->prepare('SELECT id, raison_sociale FROM entreprises WHERE org_id = ?');
$stmt->execute([$oid]);
$entreprisesMap = [];
foreach ($stmt->fetchAll() as $e) {
    $key = mb_strtolower(trim($e['raison_sociale']));
    $entreprisesMap[$key] = $e;
}

// ── Process rows ──
$created  = 0;
$skipped  = 0;
$errors   = [];

// Prepare matricule lookup per entreprise prefix
$matriculeCache = [];

foreach ($rows as $idx => $row) {
    $lineNum = $idx + 1;

    $nom          = trim($row['nom'] ?? $row[0] ?? '');
    $prenom       = trim($row['prenom'] ?? $row[1] ?? '');
    $entreprise   = trim($row['entreprise'] ?? $row[2] ?? '');
    $sexe         = mb_strtolower(trim($row['sexe'] ?? $row[3] ?? 'masculin'));
    $dateNaiss    = trim($row['date_naissance'] ?? $row[4] ?? '');
    $telephone    = trim($row['telephone'] ?? $row[5] ?? '');
    $categorie    = mb_strtolower(trim($row['categorie'] ?? $row[6] ?? 'titulaire'));
    $plafond      = (int)($row['plafond_annuel'] ?? $row[7] ?? 0);
    $dateAdhesion = trim($row['date_adhesion'] ?? $row[8] ?? '');
    $statut       = mb_strtolower(trim($row['statut'] ?? $row[9] ?? 'actif'));

    // Skip empty rows
    if ($nom === '' && $prenom === '') {
        continue;
    }

    // Validate required fields
    if ($nom === '' || $prenom === '') {
        $errors[] = "Ligne $lineNum : Nom et prénom requis";
        $skipped++;
        continue;
    }

    // Match entreprise
    $entKey = mb_strtolower($entreprise);
    if (!isset($entreprisesMap[$entKey])) {
        $errors[] = "Ligne $lineNum : Entreprise '$entreprise' introuvable";
        $skipped++;
        continue;
    }
    $entId   = (int)$entreprisesMap[$entKey]['id'];
    $entName = $entreprisesMap[$entKey]['raison_sociale'];

    // Validate enums
    if (!in_array($sexe, ['masculin', 'feminin'])) {
        $sexe = 'masculin';
    }
    if (!in_array($categorie, ['titulaire', 'conjoint', 'enfant'])) {
        $categorie = 'titulaire';
    }
    if (!in_array($statut, ['actif', 'inactif', 'suspendu'])) {
        $statut = 'actif';
    }

    // Validate dates
    if ($dateNaiss && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaiss)) {
        // Try DD/MM/YYYY format
        if (preg_match('#^(\d{2})[/\-](\d{2})[/\-](\d{4})$#', $dateNaiss, $m)) {
            $dateNaiss = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $dateNaiss = null;
        }
    }
    if ($dateAdhesion && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAdhesion)) {
        if (preg_match('#^(\d{2})[/\-](\d{2})[/\-](\d{4})$#', $dateAdhesion, $m)) {
            $dateAdhesion = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $dateAdhesion = null;
        }
    }

    // Auto-generate matricule
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $entName), 0, 3));

    if (!isset($matriculeCache[$prefix])) {
        $stmt2 = db()->prepare("
            SELECT matricule FROM adherents
            WHERE org_id = ? AND matricule LIKE ?
            ORDER BY matricule DESC LIMIT 1
        ");
        $stmt2->execute([$oid, $prefix . '-%']);
        $last = $stmt2->fetchColumn();
        $matriculeCache[$prefix] = $last ? (int)substr($last, strlen($prefix) + 1) : 0;
    }

    $matriculeCache[$prefix]++;
    $matricule = $prefix . '-' . str_pad($matriculeCache[$prefix], 3, '0', STR_PAD_LEFT);

    // Insert
    try {
        $stmt3 = db()->prepare('
            INSERT INTO adherents (org_id, entreprise_id, nom, prenom, matricule, sexe, date_naissance, telephone, categorie, plafond_annuel, date_adhesion, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt3->execute([
            $oid,
            $entId,
            $nom,
            $prenom,
            $matricule,
            $sexe,
            $dateNaiss ?: null,
            $telephone ?: null,
            $categorie,
            $plafond,
            $dateAdhesion ?: null,
            $statut,
        ]);
        $created++;
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            $errors[] = "Ligne $lineNum : Matricule en doublon, ignoré";
            $skipped++;
        } else {
            $errors[] = "Ligne $lineNum : " . $e->getMessage();
            $skipped++;
        }
    }
}

jsonResponse([
    'ok'      => true,
    'created' => $created,
    'skipped' => $skipped,
    'errors'  => array_slice($errors, 0, 20), // limit to 20 error messages
]);


// ─────────────────────────────────────────────────────────
// CSV Parser
// ─────────────────────────────────────────────────────────
function parseCsv(string $filePath): array
{
    $rows = [];

    // Detect delimiter by reading first line
    $firstLine = file_get_contents($filePath, false, null, 0, 4096);
    // Remove BOM
    $firstLine = ltrim($firstLine, "\xEF\xBB\xBF");
    $delimiter  = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

    $handle = fopen($filePath, 'r');
    if (!$handle) return [];

    // Skip BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers = null;
    $headerMap = [
        'nom'             => 'nom',
        'prenom'          => 'prenom',
        'prénom'          => 'prenom',
        'entreprise'      => 'entreprise',
        'sexe'            => 'sexe',
        'date de naissance'=> 'date_naissance',
        'date_naissance'  => 'date_naissance',
        'telephone'       => 'telephone',
        'téléphone'       => 'telephone',
        'categorie'       => 'categorie',
        'catégorie'       => 'categorie',
        'plafond annuel'  => 'plafond_annuel',
        'plafond_annuel'  => 'plafond_annuel',
        'date adhesion'   => 'date_adhesion',
        'date_adhesion'   => 'date_adhesion',
        "date d'adhesion" => 'date_adhesion',
        'statut'          => 'statut',
    ];

    $lineNum = 0;
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNum++;

        // Skip empty lines
        if (count($data) <= 1 && empty(trim($data[0] ?? ''))) continue;

        // Skip instruction/comment rows (starts with * or ---)
        $first = trim($data[0] ?? '');
        if (str_starts_with($first, '*') || str_starts_with($first, '---')) continue;

        // Detect header row
        if ($headers === null) {
            $isHeader = false;
            foreach ($data as $cell) {
                $key = mb_strtolower(trim($cell));
                if (isset($headerMap[$key])) {
                    $isHeader = true;
                    break;
                }
            }

            if ($isHeader) {
                $headers = [];
                foreach ($data as $i => $cell) {
                    $key = mb_strtolower(trim($cell));
                    $headers[$i] = $headerMap[$key] ?? "col_$i";
                }
                continue;
            }
        }

        // Data row
        if ($headers) {
            $row = [];
            foreach ($headers as $i => $name) {
                $row[$name] = $data[$i] ?? '';
            }
            $rows[] = $row;
        } else {
            // No headers detected — use positional
            $rows[] = $data;
        }
    }

    fclose($handle);
    return $rows;
}


// ─────────────────────────────────────────────────────────
// XLSX Parser (lightweight, no external library)
// ─────────────────────────────────────────────────────────
function parseXlsx(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        jsonResponse(['error' => 'Extension ZIP requise pour les fichiers XLSX'], 500);
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        jsonResponse(['error' => 'Impossible de lire le fichier XLSX'], 422);
    }

    // Read shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDoc = new DOMDocument();
        $ssDoc->loadXML($ssXml);
        $siNodes = $ssDoc->getElementsByTagName('si');
        foreach ($siNodes as $si) {
            $text = '';
            $tNodes = $si->getElementsByTagName('t');
            foreach ($tNodes as $t) {
                $text .= $t->textContent;
            }
            $sharedStrings[] = $text;
        }
    }

    // Read first sheet (sheet1.xml)
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        $zip->close();
        jsonResponse(['error' => 'Aucune feuille trouvée dans le fichier XLSX'], 422);
    }

    $doc = new DOMDocument();
    $doc->loadXML($sheetXml);
    $rows = [];
    $rowNodes = $doc->getElementsByTagName('row');

    foreach ($rowNodes as $rowNode) {
        $rowData = [];
        $cells = $rowNode->getElementsByTagName('c');

        foreach ($cells as $cell) {
            $colRef = $cell->getAttribute('r');
            // Extract column letter(s)
            preg_match('/^([A-Z]+)/', $colRef, $m);
            $colIdx = columnToIndex($m[1] ?? 'A');

            $type  = $cell->getAttribute('t');
            $vNode = $cell->getElementsByTagName('v')->item(0);
            $value = $vNode ? $vNode->textContent : '';

            if ($type === 's' && isset($sharedStrings[(int)$value])) {
                $value = $sharedStrings[(int)$value];
            }

            // Pad array to reach this column
            while (count($rowData) < $colIdx) {
                $rowData[] = '';
            }
            $rowData[$colIdx] = $value;
        }

        if (!empty(array_filter($rowData, fn($v) => trim($v) !== ''))) {
            $rows[] = $rowData;
        }
    }

    $zip->close();

    // Use parseCsv-style header detection on the array rows
    return mapXlsxRows($rows);
}

function columnToIndex(string $col): int
{
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function mapXlsxRows(array $rawRows): array
{
    if (empty($rawRows)) return [];

    $headerMap = [
        'nom'             => 'nom',
        'prenom'          => 'prenom',
        'prénom'          => 'prenom',
        'entreprise'      => 'entreprise',
        'sexe'            => 'sexe',
        'date de naissance'=> 'date_naissance',
        'date_naissance'  => 'date_naissance',
        'telephone'       => 'telephone',
        'téléphone'       => 'telephone',
        'categorie'       => 'categorie',
        'catégorie'       => 'categorie',
        'plafond annuel'  => 'plafond_annuel',
        'plafond_annuel'  => 'plafond_annuel',
        'date adhesion'   => 'date_adhesion',
        'date_adhesion'   => 'date_adhesion',
        "date d'adhesion" => 'date_adhesion',
        'statut'          => 'statut',
    ];

    $headers = null;
    $result  = [];

    foreach ($rawRows as $row) {
        $first = trim($row[0] ?? '');
        if (str_starts_with($first, '*') || str_starts_with($first, '---')) continue;

        if ($headers === null) {
            // Try to detect header row
            $isHeader = false;
            foreach ($row as $cell) {
                $key = mb_strtolower(trim($cell));
                if (isset($headerMap[$key])) {
                    $isHeader = true;
                    break;
                }
            }

            if ($isHeader) {
                $headers = [];
                foreach ($row as $i => $cell) {
                    $key = mb_strtolower(trim($cell));
                    $headers[$i] = $headerMap[$key] ?? "col_$i";
                }
                continue;
            }
        }

        if ($headers) {
            $mapped = [];
            foreach ($headers as $i => $name) {
                $mapped[$name] = $row[$i] ?? '';
            }
            $result[] = $mapped;
        } else {
            $result[] = $row;
        }
    }

    return $result;
}
