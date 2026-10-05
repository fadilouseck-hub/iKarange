<?php

/**
 * GET /api/tiers-payant/facturation
 * Returns PECs for this prestataire grouped by enterprise.
 * Params: ?date_debut=, ?date_fin=, ?format=print (for printable invoice)
 */

$presta = requirePrestataire();
$oid    = prestaOrgId();
$prestaId = (int)$presta['prestataire_id'];

$dateDebut = trim($_GET['date_debut'] ?? '');
$dateFin   = trim($_GET['date_fin'] ?? '');
$format    = trim($_GET['format'] ?? '');

$where = ['pec.org_id = ?', 'pec.prestataire_id = ?'];
$params = [$oid, $prestaId];

if ($dateDebut) {
    $where[] = 'pec.date_soins >= ?';
    $params[] = $dateDebut;
}
if ($dateFin) {
    $where[] = 'pec.date_soins <= ?';
    $params[] = $dateFin;
}

// Only include approved/billed/settled PECs for invoicing
$where[] = "pec.statut IN ('approuvee','facturee','reglee')";

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT pec.id, pec.numero, pec.date_soins, pec.type_acte, pec.montant_total,
           pec.taux_couverture, pec.part_ipm, pec.part_adherent, pec.motif, pec.statut,
           a.nom as adherent_nom, a.prenom as adherent_prenom, a.matricule,
           e.id as entreprise_id, e.raison_sociale as entreprise_nom
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE $whereStr
    ORDER BY e.raison_sociale, pec.date_soins
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Group by enterprise
$grouped = [];
$grandTotal = 0;
$grandTotalIpm = 0;

foreach ($rows as $row) {
    $eid = $row['entreprise_id'] ?? 0;
    $ename = $row['entreprise_nom'] ?? 'Sans entreprise';

    if (!isset($grouped[$eid])) {
        $grouped[$eid] = [
            'entreprise_id'  => $eid,
            'entreprise_nom' => $ename,
            'total_montant'  => 0,
            'total_ipm'      => 0,
            'nb_pec'         => 0,
            'pecs'           => [],
        ];
    }

    $grouped[$eid]['pecs'][] = $row;
    $grouped[$eid]['total_montant'] += (int)$row['montant_total'];
    $grouped[$eid]['total_ipm'] += (int)$row['part_ipm'];
    $grouped[$eid]['nb_pec']++;
    $grandTotal += (int)$row['montant_total'];
    $grandTotalIpm += (int)$row['part_ipm'];
}

$grouped = array_values($grouped);

// If format=print, generate printable invoice HTML with page breaks
if ($format === 'print') {
    $prestaName = $presta['prestataire_nom'] ?? '';

    // Get org name
    $stmt = db()->prepare('SELECT name FROM organizations WHERE id = ?');
    $stmt->execute([$oid]);
    $orgName = $stmt->fetchColumn() ?: "I'KARANGE";

    $periodeLabel = '';
    if ($dateDebut && $dateFin) $periodeLabel = 'Du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin));
    elseif ($dateDebut) $periodeLabel = 'A partir du ' . date('d/m/Y', strtotime($dateDebut));
    elseif ($dateFin) $periodeLabel = "Jusqu'au " . date('d/m/Y', strtotime($dateFin));
    else $periodeLabel = 'Toutes les dates';

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">';
    echo '<title>Facturation - ' . e($prestaName) . '</title>';
    echo '<style>';
    echo '* { box-sizing: border-box; margin: 0; padding: 0; }';
    echo 'body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }';
    echo '@media print { @page { size: A4; margin: 12mm; } .no-print { display: none; } .page-break { page-break-before: always; } }';
    echo '.page { padding: 20px; }';
    echo '.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a3a3a; padding-bottom: 10px; margin-bottom: 12px; }';
    echo '.header .logo { font-size: 18px; font-weight: 700; color: #2dd4a8; }';
    echo '.header .org { font-size: 12px; font-weight: 600; color: #1a3a3a; }';
    echo '.header .right { text-align: right; font-size: 10px; color: #888; }';
    echo '.ent-title { background: #1a3a3a; color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; margin-bottom: 10px; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }';
    echo 'th { background: #f0f0f0; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; border-bottom: 2px solid #ddd; }';
    echo 'td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 10px; }';
    echo 'tr:nth-child(even) { background: #fafafa; }';
    echo '.totals { background: #f8f8f8; border: 1px solid #ddd; border-radius: 6px; padding: 10px 15px; display: flex; justify-content: flex-end; gap: 30px; font-size: 12px; margin-bottom: 20px; }';
    echo '.totals .amount { font-weight: 700; font-size: 14px; }';
    echo '.totals .ipm { color: #2dd4a8; }';
    echo '.footer { text-align: center; font-size: 8px; color: #aaa; border-top: 1px solid #e0e0e0; padding-top: 6px; margin-top: 15px; }';
    echo '.no-print { text-align: center; padding: 15px; }';
    echo '</style></head><body>';

    echo '<div class="no-print"><button onclick="window.print()" style="padding:8px 24px;background:#2dd4a8;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">Imprimer / PDF</button></div>';

    foreach ($grouped as $idx => $group) {
        if ($idx > 0) echo '<div class="page-break"></div>';

        echo '<div class="page">';
        echo '<div class="header"><div><div class="logo">I\'KARANG&Eacute;</div><div class="org">' . e($orgName) . '</div></div>';
        echo '<div class="right"><strong>FACTURE PRESTATAIRE</strong><br>' . e($prestaName) . '<br>' . $periodeLabel . '<br>Page ' . ($idx + 1) . '/' . count($grouped) . '</div></div>';

        echo '<div class="ent-title">' . e($group['entreprise_nom']) . ' (' . $group['nb_pec'] . ' prestation(s))</div>';

        echo '<table><thead><tr><th>N&deg;</th><th>Date</th><th>Adh&eacute;rent</th><th>Matricule</th><th>Acte</th><th>Motif</th><th style="text-align:right;">Montant</th><th style="text-align:right;">Part IPM</th></tr></thead><tbody>';

        foreach ($group['pecs'] as $pec) {
            echo '<tr>';
            echo '<td>' . e($pec['numero']) . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($pec['date_soins'])) . '</td>';
            echo '<td>' . e($pec['adherent_nom'] . ' ' . $pec['adherent_prenom']) . '</td>';
            echo '<td>' . e($pec['matricule']) . '</td>';
            echo '<td>' . e($pec['type_acte']) . '</td>';
            echo '<td>' . e($pec['motif'] ?? '') . '</td>';
            echo '<td style="text-align:right;">' . number_format($pec['montant_total'], 0, ',', ' ') . ' F</td>';
            echo '<td style="text-align:right;font-weight:600;color:#2dd4a8;">' . number_format($pec['part_ipm'], 0, ',', ' ') . ' F</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<div class="totals">';
        echo '<div>Total montant: <span class="amount">' . number_format($group['total_montant'], 0, ',', ' ') . ' F</span></div>';
        echo '<div>Total Part IPM: <span class="amount ipm">' . number_format($group['total_ipm'], 0, ',', ' ') . ' F</span></div>';
        echo '</div>';

        echo '<div class="footer">I\'KARANG&Eacute; &mdash; ' . e($orgName) . ' | ' . e($prestaName) . ' | Document g&eacute;n&eacute;r&eacute; le ' . date('d/m/Y') . '</div>';
        echo '</div>';
    }

    // Grand total summary page
    if (count($grouped) > 1) {
        echo '<div class="page-break"></div><div class="page">';
        echo '<div class="header"><div><div class="logo">I\'KARANG&Eacute;</div><div class="org">' . e($orgName) . '</div></div>';
        echo '<div class="right"><strong>RECAPITULATIF</strong><br>' . e($prestaName) . '<br>' . $periodeLabel . '</div></div>';

        echo '<table><thead><tr><th>Entreprise</th><th style="text-align:right;">Nb PEC</th><th style="text-align:right;">Montant Total</th><th style="text-align:right;">Part IPM (ch&egrave;que)</th></tr></thead><tbody>';
        foreach ($grouped as $g) {
            echo '<tr><td style="font-weight:600;">' . e($g['entreprise_nom']) . '</td>';
            echo '<td style="text-align:right;">' . $g['nb_pec'] . '</td>';
            echo '<td style="text-align:right;">' . number_format($g['total_montant'], 0, ',', ' ') . ' F</td>';
            echo '<td style="text-align:right;font-weight:700;color:#2dd4a8;">' . number_format($g['total_ipm'], 0, ',', ' ') . ' F</td></tr>';
        }
        echo '<tr style="border-top:3px solid #1a3a3a;"><td style="font-weight:700;">TOTAL GENERAL</td>';
        echo '<td style="text-align:right;font-weight:700;">' . count($rows) . '</td>';
        echo '<td style="text-align:right;font-weight:700;">' . number_format($grandTotal, 0, ',', ' ') . ' F</td>';
        echo '<td style="text-align:right;font-weight:700;color:#2dd4a8;font-size:14px;">' . number_format($grandTotalIpm, 0, ',', ' ') . ' F</td></tr>';
        echo '</tbody></table>';

        echo '<div class="footer">I\'KARANG&Eacute; | ' . e($prestaName) . ' | ' . date('d/m/Y') . '</div>';
        echo '</div>';
    }

    echo '<script>window.onload=function(){window.print();};</script>';
    echo '</body></html>';
    exit;
}

// JSON response for the UI
jsonResponse([
    'prestataire'   => $presta['prestataire_nom'],
    'periode'       => ['debut' => $dateDebut, 'fin' => $dateFin],
    'entreprises'   => $grouped,
    'grand_total'   => $grandTotal,
    'grand_total_ipm' => $grandTotalIpm,
    'nb_pec_total'  => count($rows),
]);
