<?php

/**
 * POST /api/assistant
 * Smart search assistant — keyword-based query engine.
 *
 * Accepts JSON: { "query": "combien d'adherents actifs" }
 * Returns JSON: { type, message, value?, link?, items?, suggestions[] }
 */

$user = requireAuth();
$oid  = orgId();

if (requestMethod() !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = jsonInput();
$raw   = trim($input['query'] ?? '');

if ($raw === '') {
    jsonResponse([
        'type'        => 'greeting',
        'message'     => 'Bonjour ! Posez-moi une question sur vos donnees.',
        'suggestions' => defaultSuggestions(),
    ]);
}

$q = normalizeQuery($raw);

// Try each matcher in priority order
$result = matchNavigation($q, $raw)
       ?? matchAggregation($q, $oid)
       ?? matchStatusFilter($q, $oid)
       ?? matchCount($q, $oid)
       ?? matchEntitySearch($q, $raw, $oid)
       ?? fallbackResponse($raw);

jsonResponse($result);


// ─────────────────────────────────────────────────────────
// Normalize: lowercase, strip accents, trim
// ─────────────────────────────────────────────────────────
function normalizeQuery(string $s): string
{
    $s = mb_strtolower(trim($s));
    // Strip accents
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    // Remove punctuation except spaces and hyphens
    $s = preg_replace('/[^a-z0-9\s\-]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}


// ─────────────────────────────────────────────────────────
// 1. Navigation matcher
// ─────────────────────────────────────────────────────────
function matchNavigation(string $q, string $raw): ?array
{
    $pages = [
        'tableau de bord|dashboard|accueil'               => ['/', 'Tableau de bord', 'bx bx-grid-alt'],
        'tiers payant|tiers-payant'                       => ['/tiers-payant', 'Tiers payant', 'bx bx-heart'],
        'entreprise'                                      => ['/entreprises', 'Entreprises', 'bx bx-buildings'],
        'adherent|membre'                                 => ['/adherents', 'Adherents', 'bx bx-group'],
        'prestataire|medecin|hopital|clinique|pharmacie'  => ['/prestataires', 'Prestataires', 'bx bx-plus-medical'],
        'utilisateur.+prestataire'                        => ['/utilisateurs-prestataires', 'Utilisateurs prestataires', 'bx bx-user-check'],
        'prise en charge|pec'                             => ['/prises-en-charge', 'Prises en charge', 'bx bx-file'],
        'facture.+prestataire'                            => ['/factures-prestataires', 'Factures prestataires', 'bx bx-receipt'],
        'facture.+entreprise'                             => ['/factures-entreprises', 'Factures entreprises', 'bx bx-spreadsheet'],
        'prime|budget'                                    => ['/primes-budget', 'Primes & Budget', 'bx bx-wallet'],
        'statistique|stat'                                 => ['/statistiques', 'Statistiques', 'bx bx-bar-chart-alt-2'],
        'rapport|reporting|etat|export'                    => ['/rapports', 'Rapports', 'bx bx-printer'],
        'remboursement|rembourser|rembourse'               => ['/remboursements', 'Remboursements', 'bx bx-money'],
        'compagnie|assurance|reassurance'                 => ['/compagnies-assurance', 'Compagnies d\'assurance', 'bx bx-shield-quarter'],
        'bareme|tarif|grille'                              => ['/entreprises', 'Baremes (via Entreprises)', 'bx bx-list-check'],
        'acces|utilisateur|profil|permission|role'        => ['/gestion-acces', 'Gestion des acces', 'bx bx-lock-open-alt'],
    ];

    // Navigation intent keywords
    $navIntents = ['ou|aller|page|voir|ouvrir|afficher|acceder|trouver|naviguer|chercher|menu'];
    $hasNavIntent = false;
    foreach ($navIntents as $pattern) {
        if (preg_match('/\b(' . $pattern . ')\b/', $q)) {
            $hasNavIntent = true;
            break;
        }
    }

    // Skip navigation if user is asking a data question
    $hasDataIntent = (bool)preg_match('/\b(combien|nombre|total|montant|somme|count|nb|impaye|depense|cout|moyenne|moyen|plafond|actif|actifs|inactif|suspendu|en.attente|approuve|rejete|payee?|reglee?)\b/', $q);

    if ($hasDataIntent) {
        return null;
    }

    foreach ($pages as $pattern => $info) {
        if (preg_match('/\b(' . $pattern . ')/', $q)) {
            if ($hasNavIntent || str_word_count($q) <= 3) {
                return [
                    'type'        => 'navigation',
                    'message'     => 'Voici la page : ' . $info[1],
                    'link'        => $info[0],
                    'linkLabel'   => $info[1],
                    'icon'        => $info[2],
                    'suggestions' => contextSuggestions($info[0]),
                ];
            }
            break;
        }
    }

    return null;
}


// ─────────────────────────────────────────────────────────
// 2. Count matcher
// ─────────────────────────────────────────────────────────
function matchCount(string $q, int $oid): ?array
{
    if (!preg_match('/\b(combien|nombre|total|count|nb)\b/', $q)) {
        return null;
    }

    $entities = [
        'adherent|membre'                  => ['adherents', 'adherents', '/adherents'],
        'entreprise|societe|client'        => ['entreprises', 'entreprises', '/entreprises'],
        'prestataire|medecin'              => ['prestataires', 'prestataires', '/prestataires'],
        'prise en charge|pec'              => ['prises en charge', 'prises_en_charge', '/prises-en-charge'],
        'facture.+prestataire'             => ['factures prestataires', 'factures_prestataires', '/factures-prestataires'],
        'facture.+entreprise'              => ['factures entreprises', 'factures_entreprises', '/factures-entreprises'],
        'facture'                          => ['factures (toutes)', null, null],
        'prime|cotisation'                 => ['primes', 'primes', '/primes-budget'],
        'compagnie|assurance'              => ['compagnies', 'compagnies_assurance', '/compagnies-assurance'],
        'bareme|tarif|grille'              => ['baremes', 'baremes', '/entreprises'],
        'remboursement'                    => ['remboursements', 'remboursements', '/remboursements'],
        'utilisateur'                      => ['utilisateurs', 'users', null],
    ];

    foreach ($entities as $pattern => $info) {
        if (preg_match('/\b(' . $pattern . ')/', $q)) {
            $label = $info[0];
            $table = $info[1];
            $link  = $info[2];

            if ($table === null) {
                // Special: all invoices
                $stmt1 = db()->prepare('SELECT COUNT(*) FROM factures_prestataires WHERE org_id = ?');
                $stmt1->execute([$oid]);
                $c1 = (int)$stmt1->fetchColumn();
                $stmt2 = db()->prepare('SELECT COUNT(*) FROM factures_entreprises WHERE org_id = ?');
                $stmt2->execute([$oid]);
                $c2 = (int)$stmt2->fetchColumn();
                $count = $c1 + $c2;
            } else {
                $stmt = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE org_id = ?");
                $stmt->execute([$oid]);
                $count = (int)$stmt->fetchColumn();
            }

            $result = [
                'type'        => 'count',
                'message'     => "Vous avez $count $label.",
                'value'       => $count,
                'label'       => $label,
                'suggestions' => [],
            ];

            if ($link) {
                $result['link']      = $link;
                $result['linkLabel'] = 'Voir la liste';
            }

            $result['suggestions'] = countFollowUp($pattern);

            return $result;
        }
    }

    return null;
}


// ─────────────────────────────────────────────────────────
// 3. Aggregation matcher
// ─────────────────────────────────────────────────────────
function matchAggregation(string $q, int $oid): ?array
{
    // Total primes/cotisations
    if (preg_match('/\b(total|montant|somme).+(prime|cotisation|budget)/', $q)
        || preg_match('/\b(prime|cotisation|budget).+(total|montant|somme)/', $q)) {
        $stmt = db()->prepare('SELECT COALESCE(SUM(montant), 0) FROM primes WHERE org_id = ?');
        $stmt->execute([$oid]);
        $total = (int)$stmt->fetchColumn();

        return [
            'type'        => 'aggregation',
            'message'     => 'Total des primes : ' . number_format($total, 0, ',', ' ') . ' F CFA.',
            'value'       => $total,
            'formatted'   => number_format($total, 0, ',', ' ') . ' F',
            'label'       => 'Total primes',
            'link'        => '/primes-budget',
            'linkLabel'   => 'Voir Primes & Budget',
            'suggestions' => ['Combien d\'adherents ?', 'Factures impayees'],
        ];
    }

    // Unpaid invoices (prestataires)
    if (preg_match('/\b(impaye|non payee?|en.attente|a.payer).*(facture|fact)/', $q)
        || preg_match('/\b(facture|fact).*(impaye|non payee?|en.attente|a.payer)/', $q)) {
        $stmt = db()->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(montant_total), 0) as total FROM factures_prestataires WHERE org_id = ? AND statut IN ('a_facturer', 'en_attente')");
        $stmt->execute([$oid]);
        $row = $stmt->fetch();

        return [
            'type'        => 'aggregation',
            'message'     => $row['cnt'] . ' facture(s) impayee(s) pour un total de ' . number_format($row['total'], 0, ',', ' ') . ' F CFA.',
            'value'       => (int)$row['total'],
            'formatted'   => number_format($row['total'], 0, ',', ' ') . ' F',
            'label'       => 'Factures impayees',
            'link'        => '/factures-prestataires',
            'linkLabel'   => 'Voir les factures',
            'suggestions' => ['Total des primes', 'Combien de PEC ?'],
        ];
    }

    // Total depenses / factures
    if (preg_match('/\b(depense|cout|charge|total.+facture)/', $q)) {
        $stmt1 = db()->prepare('SELECT COALESCE(SUM(montant_total), 0) FROM factures_prestataires WHERE org_id = ?');
        $stmt1->execute([$oid]);
        $t1 = (int)$stmt1->fetchColumn();

        return [
            'type'        => 'aggregation',
            'message'     => 'Total des factures prestataires : ' . number_format($t1, 0, ',', ' ') . ' F CFA.',
            'value'       => $t1,
            'formatted'   => number_format($t1, 0, ',', ' ') . ' F',
            'label'       => 'Total factures',
            'link'        => '/factures-prestataires',
            'linkLabel'   => 'Voir les factures',
            'suggestions' => ['Factures impayees', 'Combien de prestataires ?'],
        ];
    }

    // Plafond moyen
    if (preg_match('/\b(plafond|moyenne|moyen).+(adherent|membre)/', $q)
        || preg_match('/\b(adherent|membre).+(plafond|moyenne|moyen)/', $q)) {
        $stmt = db()->prepare('SELECT COALESCE(AVG(plafond_annuel), 0) FROM adherents WHERE org_id = ? AND plafond_annuel > 0');
        $stmt->execute([$oid]);
        $avg = (int)$stmt->fetchColumn();

        return [
            'type'        => 'aggregation',
            'message'     => 'Plafond annuel moyen : ' . number_format($avg, 0, ',', ' ') . ' F CFA.',
            'value'       => $avg,
            'formatted'   => number_format($avg, 0, ',', ' ') . ' F',
            'label'       => 'Plafond moyen',
            'link'        => '/adherents',
            'linkLabel'   => 'Voir les adherents',
            'suggestions' => ['Combien d\'adherents actifs ?', 'Total des primes'],
        ];
    }

    return null;
}


// ─────────────────────────────────────────────────────────
// 4. Status filter matcher
// ─────────────────────────────────────────────────────────
function matchStatusFilter(string $q, int $oid): ?array
{
    $statusEntities = [
        'adherent|membre' => [
            'table'    => 'adherents',
            'label'    => 'adherents',
            'link'     => '/adherents',
            'statuses' => ['actif', 'inactif', 'suspendu'],
        ],
        'prise en charge|pec' => [
            'table'    => 'prises_en_charge',
            'label'    => 'prises en charge',
            'link'     => '/prises-en-charge',
            'statuses' => ['en_attente', 'approuvee', 'rejetee', 'facturee'],
        ],
        'facture.+prestataire|fact.+prest' => [
            'table'    => 'factures_prestataires',
            'label'    => 'factures prestataires',
            'link'     => '/factures-prestataires',
            'statuses' => ['a_facturer', 'en_attente', 'reglee', 'payee'],
        ],
        'facture.+entreprise|fact.+entr' => [
            'table'    => 'factures_entreprises',
            'label'    => 'factures entreprises',
            'link'     => '/factures-entreprises',
            'statuses' => ['en_attente', 'reglee', 'payee'],
        ],
        'entreprise|societe' => [
            'table'    => 'entreprises',
            'label'    => 'entreprises',
            'link'     => '/entreprises',
            'statuses' => ['active', 'resilie'],
        ],
        'prestataire' => [
            'table'    => 'prestataires',
            'label'    => 'prestataires',
            'link'     => '/prestataires',
            'statuses' => ['agree', 'suspendu', 'resilie'],
        ],
    ];

    $statusMap = [
        'actif'       => 'actif',
        'actifs'      => 'actif',
        'active'      => 'active',
        'actives'     => 'active',
        'inactif'     => 'inactif',
        'inactifs'    => 'inactif',
        'suspendu'    => 'suspendu',
        'suspendus'   => 'suspendu',
        'en attente'  => 'en_attente',
        'en.attente'  => 'en_attente',
        'approuve'    => 'approuvee',
        'approuvee'   => 'approuvee',
        'approuvees'  => 'approuvee',
        'rejete'      => 'rejetee',
        'rejetee'     => 'rejetee',
        'rejetees'    => 'rejetee',
        'facture'     => 'facturee',
        'facturee'    => 'facturee',
        'facturees'   => 'facturee',
        'payee'       => 'payee',
        'payees'      => 'payee',
        'paye'        => 'payee',
        'reglee'      => 'reglee',
        'reglees'     => 'reglee',
        'agree'       => 'agree',
        'agrees'      => 'agree',
        'resilie'     => 'resilie',
        'resilies'    => 'resilie',
        'a facturer'  => 'a_facturer',
        'impaye'      => 'en_attente',
        'impayes'     => 'en_attente',
        'impayee'     => 'en_attente',
        'impayees'    => 'en_attente',
    ];

    // Find a status keyword in the query
    $foundStatus = null;
    $foundStatusDb = null;
    foreach ($statusMap as $keyword => $dbVal) {
        $kw = preg_replace('/\s+/', '.', $keyword);
        if (preg_match('/\b' . $kw . '\b/', $q)) {
            $foundStatus = $keyword;
            $foundStatusDb = $dbVal;
            break;
        }
    }

    if ($foundStatus === null) {
        return null;
    }

    // Find the entity
    foreach ($statusEntities as $pattern => $info) {
        if (preg_match('/\b(' . $pattern . ')/', $q)) {
            if (!in_array($foundStatusDb, $info['statuses'])) {
                continue;
            }

            $table = $info['table'];
            $stmt = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE org_id = ? AND statut = ?");
            $stmt->execute([$oid, $foundStatusDb]);
            $count = (int)$stmt->fetchColumn();

            return [
                'type'        => 'status',
                'message'     => "$count {$info['label']} avec le statut \"$foundStatusDb\".",
                'value'       => $count,
                'label'       => $info['label'] . ' ' . $foundStatusDb,
                'link'        => $info['link'],
                'linkLabel'   => 'Voir la liste',
                'suggestions' => ['Combien de ' . $info['label'] . ' ?', 'Total des primes'],
            ];
        }
    }

    return null;
}


// ─────────────────────────────────────────────────────────
// 5. Entity search matcher (LIKE search)
// ─────────────────────────────────────────────────────────
function matchEntitySearch(string $q, string $raw, int $oid): ?array
{
    // Need at least 2 characters of meaningful search term
    $searchTerm = trim($raw);
    if (mb_strlen($searchTerm) < 2) {
        return null;
    }

    // Skip if it looks like a question/command rather than a name search
    if (preg_match('/\b(combien|nombre|total|montant|somme|ou|aller|page|voir|ouvrir)\b/', $q)) {
        return null;
    }

    $like = '%' . $searchTerm . '%';
    $items = [];

    // Search adherents
    $stmt = db()->prepare("
        SELECT a.id, CONCAT(a.nom, ' ', a.prenom) as label, a.matricule as sub, 'adherent' as entity
        FROM adherents a
        WHERE a.org_id = ? AND (a.nom LIKE ? OR a.prenom LIKE ? OR a.matricule LIKE ? OR a.telephone LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$oid, $like, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'label'  => $r['label'],
            'sub'    => 'Matricule: ' . $r['sub'],
            'icon'   => 'bx bx-user',
            'link'   => '/adherents',
            'entity' => 'Adherent',
        ];
    }

    // Search entreprises
    $stmt = db()->prepare("
        SELECT id, raison_sociale as label, secteur_activite as sub, 'entreprise' as entity
        FROM entreprises
        WHERE org_id = ? AND (raison_sociale LIKE ? OR secteur_activite LIKE ? OR telephone LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$oid, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'label'  => $r['label'],
            'sub'    => $r['sub'] ?: 'Entreprise',
            'icon'   => 'bx bx-buildings',
            'link'   => '/entreprises',
            'entity' => 'Entreprise',
        ];
    }

    // Search prestataires
    $stmt = db()->prepare("
        SELECT id, nom as label, type as sub, 'prestataire' as entity
        FROM prestataires
        WHERE org_id = ? AND (nom LIKE ? OR type LIKE ? OR ville LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$oid, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'label'  => $r['label'],
            'sub'    => ucfirst($r['sub'] ?: 'Prestataire'),
            'icon'   => 'bx bx-plus-medical',
            'link'   => '/prestataires',
            'entity' => 'Prestataire',
        ];
    }

    // Search baremes
    $stmt = db()->prepare("
        SELECT b.id, e.raison_sociale as label, CONCAT(b.libelle, ' - ', b.taux_couverture, '%') as sub, 'bareme' as entity
        FROM baremes b
        JOIN entreprises e ON e.id = b.entreprise_id
        WHERE b.org_id = ? AND (e.raison_sociale LIKE ? OR b.type_acte LIKE ? OR b.libelle LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$oid, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'label'  => $r['label'],
            'sub'    => $r['sub'],
            'icon'   => 'bx bx-list-check',
            'link'   => '/entreprises',
            'entity' => 'Bareme',
        ];
    }

    // Search PEC
    $stmt = db()->prepare("
        SELECT p.id, p.numero as label, CONCAT(a.nom, ' ', a.prenom) as sub, 'pec' as entity
        FROM prises_en_charge p
        LEFT JOIN adherents a ON a.id = p.adherent_id
        WHERE p.org_id = ? AND (p.numero LIKE ? OR a.nom LIKE ? OR a.prenom LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$oid, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'label'  => $r['label'],
            'sub'    => $r['sub'] ?: 'PEC',
            'icon'   => 'bx bx-file',
            'link'   => '/prises-en-charge/' . $r['id'],
            'entity' => 'Prise en charge',
        ];
    }

    if (empty($items)) {
        return null;
    }

    $count = count($items);
    return [
        'type'        => 'search',
        'message'     => "$count resultat(s) pour \"$searchTerm\".",
        'items'       => $items,
        'suggestions' => ['Combien d\'adherents ?', 'Page entreprises'],
    ];
}


// ─────────────────────────────────────────────────────────
// 6. Fallback
// ─────────────────────────────────────────────────────────
function fallbackResponse(string $raw): array
{
    return [
        'type'        => 'fallback',
        'message'     => "Je n'ai pas compris \"$raw\". Essayez une question comme :",
        'suggestions' => defaultSuggestions(),
    ];
}


// ─────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────
function defaultSuggestions(): array
{
    return [
        'Combien d\'adherents ?',
        'Factures impayees',
        'Page statistiques',
        'Total des primes',
    ];
}

function countFollowUp(string $entity): array
{
    if (str_contains($entity, 'adherent')) {
        return ['Adherents actifs', 'Plafond moyen', 'Page adherents'];
    }
    if (str_contains($entity, 'entreprise')) {
        return ['Entreprises actives', 'Combien d\'adherents ?'];
    }
    if (str_contains($entity, 'pec') || str_contains($entity, 'prise')) {
        return ['PEC en attente', 'PEC approuvees'];
    }
    if (str_contains($entity, 'facture')) {
        return ['Factures impayees', 'Total factures'];
    }
    return defaultSuggestions();
}

function contextSuggestions(string $link): array
{
    $map = [
        '/adherents'             => ['Combien d\'adherents ?', 'Adherents actifs', 'Plafond moyen'],
        '/entreprises'           => ['Combien d\'entreprises ?', 'Entreprises actives', 'Combien de baremes ?'],
        '/prestataires'          => ['Combien de prestataires ?', 'Prestataires agrees'],
        '/prises-en-charge'      => ['Combien de PEC ?', 'PEC en attente'],
        '/factures-prestataires' => ['Factures impayees', 'Total factures'],
        '/factures-entreprises'  => ['Factures en attente', 'Total factures entreprises'],
        '/primes-budget'         => ['Total des primes', 'Combien de primes ?'],
        '/statistiques'          => ['Combien d\'adherents ?', 'Total des primes'],
    ];
    return $map[$link] ?? defaultSuggestions();
}
