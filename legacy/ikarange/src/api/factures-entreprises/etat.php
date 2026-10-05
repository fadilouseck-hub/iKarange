<?php

/**
 * GET /api/factures-entreprises/{id}/etat
 * Render a printable HTML "État de Facturation Entreprise" document.
 * Opens in a new tab; user prints to PDF via browser (Ctrl+P).
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// ── Fetch facture with entreprise name ──
$stmt = db()->prepare('
    SELECT fe.*, e.raison_sociale AS entreprise_nom, e.adresse AS entreprise_adresse,
           e.telephone AS entreprise_tel, e.email AS entreprise_email
    FROM factures_entreprises fe
    LEFT JOIN entreprises e ON e.id = fe.entreprise_id
    WHERE fe.id = ? AND fe.org_id = ?
');
$stmt->execute([$id, $oid]);
$facture = $stmt->fetch();

if (!$facture) {
    jsonResponse(['error' => 'Facture introuvable'], 404);
}

// ── Extract date range from observations ──
$dateDebut = null;
$dateFin   = null;

if (preg_match('/(\d{4}-\d{2}-\d{2})\s+au\s+(\d{4}-\d{2}-\d{2})/', $facture['observations'] ?? '', $m)) {
    $dateDebut = $m[1];
    $dateFin   = $m[2];
}

// Fallback: use facture date as end, 3 months before as start
if (!$dateDebut || !$dateFin) {
    $dateFin   = $facture['date_facture'] ?: date('Y-m-d');
    $dateDebut = date('Y-m-d', strtotime($dateFin . ' -3 months'));
}

// ── Fetch PEC for adherents of this entreprise within the date range ──
$stmt = db()->prepare('
    SELECT pec.date_soins, pec.type_acte, pec.montant_total,
           pec.part_ipm, pec.part_adherent,
           CONCAT(UPPER(a.nom), \' \', a.prenom) AS adherent_nom,
           a.matricule
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    WHERE pec.org_id = ?
      AND a.entreprise_id = ?
      AND pec.statut IN (\'approuvee\', \'facturee\', \'reglee\')
      AND pec.date_soins >= ?
      AND pec.date_soins <= ?
    ORDER BY pec.date_soins DESC
');
$stmt->execute([$oid, $facture['entreprise_id'], $dateDebut, $dateFin]);
$prestations = $stmt->fetchAll();

// ── Compute totals ──
$totalMontant      = 0;
$totalPartIpm      = 0;
$totalPartAdherent = 0;

foreach ($prestations as $p) {
    $totalMontant      += (int)$p['montant_total'];
    $totalPartIpm      += (int)$p['part_ipm'];
    $totalPartAdherent += (int)$p['part_adherent'];
}

$montantPaye = (int)$facture['montant_paye'];
$netAPayer   = $totalPartIpm - $montantPaye;

// ── Count distinct adherents ──
$adherentNames = array_unique(array_column($prestations, 'adherent_nom'));
$nbAdherents   = count($adherentNames);

// ── Format helpers ──
function fmtM(int $n): string {
    return number_format($n, 0, ',', ' ');
}
function fmtD(?string $d): string {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}
function fmtStatut(string $s): string {
    return match ($s) {
        'en_attente' => 'En attente',
        'validee'    => 'Validée',
        'partiel'    => 'Partiel',
        'payee'      => 'Payée',
        default      => $s,
    };
}

$now = date('d/m/Y H:i:s');
$nbPrestations = count($prestations);

// ── Render standalone HTML document ──
header('Content-Type: text/html; charset=utf-8');

?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>État <?= htmlspecialchars($facture['numero']) ?> — I'KARANGÉ</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 15mm 15mm 20mm 15mm;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
      color: #222;
      font-size: 11pt;
      line-height: 1.4;
      background: #f0f0f0;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .page {
      width: 210mm;
      min-height: 297mm;
      margin: 10mm auto;
      background: #fff;
      padding: 15mm;
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
      position: relative;
    }

    @media print {
      body { background: #fff; }
      .page {
        width: auto;
        min-height: auto;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }
      .no-print { display: none !important; }
    }

    /* ── Header ── */
    .doc-header {
      text-align: center;
      padding-bottom: 18px;
      margin-bottom: 18px;
      border-bottom: 2px solid #1a3a3a;
    }

    .doc-header h1 {
      font-size: 28pt;
      font-weight: 800;
      color: #1a3a3a;
      letter-spacing: 2px;
      margin-bottom: 2px;
    }

    .doc-header .subtitle {
      font-size: 10pt;
      color: #555;
      margin-bottom: 6px;
    }

    .doc-header .doc-type {
      font-size: 11pt;
      font-weight: 700;
      color: #1a3a3a;
      letter-spacing: 1px;
    }

    /* ── Info boxes ── */
    .info-row {
      display: flex;
      gap: 20px;
      margin-bottom: 8px;
    }

    .info-box {
      flex: 1;
      padding: 12px 16px;
    }

    .info-box h3 {
      font-size: 10pt;
      font-weight: 700;
      color: #1a3a3a;
      margin-bottom: 6px;
      letter-spacing: 0.5px;
    }

    .info-box p {
      font-size: 9.5pt;
      color: #333;
      margin: 2px 0;
    }

    .info-box .label {
      color: #666;
    }

    .statut-badge {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 10px;
      font-size: 8.5pt;
      font-weight: 600;
      margin-top: 4px;
    }
    .statut-en_attente { background: #fff3cd; color: #856404; }
    .statut-validee    { background: #d1ecf1; color: #0c5460; }
    .statut-partiel    { background: #e2e3f1; color: #383d6e; }
    .statut-payee      { background: #d4edda; color: #155724; }

    /* ── Separator ── */
    hr.sep {
      border: none;
      border-top: 1px solid #ccc;
      margin: 14px 0;
    }

    /* ── Section title ── */
    .section-title {
      font-size: 11pt;
      font-weight: 700;
      color: #1a3a3a;
      margin-bottom: 10px;
    }

    /* ── Table ── */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      font-size: 9pt;
    }

    .data-table thead th {
      background: #d6e4f0;
      color: #1a3a3a;
      font-weight: 600;
      padding: 8px 10px;
      text-align: left;
      border-bottom: 1px solid #b0c4d8;
      font-size: 8.5pt;
    }

    .data-table tbody td {
      padding: 7px 10px;
      border-bottom: 1px solid #e8e8e8;
    }

    .data-table tbody tr:last-child td {
      border-bottom: 1px solid #ccc;
    }

    .data-table .num {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .data-table tfoot td {
      padding: 8px 10px;
      font-weight: 700;
      border-top: 2px solid #1a3a3a;
      font-size: 9.5pt;
    }

    /* ── Summary box ── */
    .summary-box {
      float: right;
      border: 1px solid #ccc;
      border-collapse: collapse;
      margin-top: 6px;
      font-size: 9.5pt;
    }

    .summary-box td {
      padding: 6px 14px;
      border-bottom: 1px solid #e0e0e0;
    }

    .summary-box tr:last-child td {
      border-bottom: none;
    }

    .summary-box .total-row td {
      font-weight: 700;
      font-size: 10.5pt;
      border-top: 2px solid #1a3a3a;
      color: #1a3a3a;
    }

    .summary-box .amount {
      text-align: right;
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    /* ── Signatures ── */
    .signatures {
      clear: both;
      display: flex;
      justify-content: space-between;
      margin-top: 50px;
      padding-top: 10px;
    }

    .sig-block {
      width: 45%;
    }

    .sig-block .sig-label {
      font-size: 9pt;
      font-weight: 600;
      color: #333;
      margin-bottom: 50px;
    }

    .sig-block .sig-line {
      border-top: 1px solid #888;
      width: 100%;
    }

    /* ── Footer ── */
    .doc-footer {
      position: absolute;
      bottom: 15mm;
      left: 15mm;
      right: 15mm;
      text-align: center;
      font-size: 7.5pt;
      color: #888;
      border-top: 1px solid #ddd;
      padding-top: 8px;
    }

    @media print {
      .doc-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 8px 15mm;
      }
    }

    /* ── Print button ── */
    .print-bar {
      text-align: center;
      padding: 12px;
      background: #fff;
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .print-bar button {
      padding: 8px 24px;
      font-size: 10pt;
      font-weight: 600;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      margin: 0 4px;
    }

    .btn-print {
      background: #1a3a3a;
      color: #fff;
    }
    .btn-print:hover { background: #264a4a; }

    .btn-close-doc {
      background: #e0e0e0;
      color: #333;
    }
    .btn-close-doc:hover { background: #d0d0d0; }
  </style>
</head>
<body>

  <!-- Print toolbar (hidden on print) -->
  <div class="print-bar no-print">
    <button class="btn-print" onclick="window.print()">
      <span style="margin-right:6px;">&#128424;</span> Imprimer / Exporter PDF
    </button>
    <button class="btn-close-doc" onclick="window.close()">Fermer</button>
  </div>

  <div class="page">

    <!-- Header -->
    <div class="doc-header">
      <h1>I'KARANG&#203;</h1>
      <div class="subtitle">Gestion Assurance &amp; Mutuelle</div>
      <div class="doc-type">&Eacute;TAT DE FACTURATION ENTREPRISE</div>
    </div>

    <!-- Invoice + Entreprise info -->
    <div class="info-row">
      <div class="info-box">
        <h3>FACTURE</h3>
        <p>N&deg;: <?= htmlspecialchars($facture['numero']) ?></p>
        <p>Date: <?= fmtD($facture['date_facture']) ?></p>
        <p>
          <span class="statut-badge statut-<?= htmlspecialchars($facture['statut']) ?>">
            Statut: <?= fmtStatut($facture['statut']) ?>
          </span>
        </p>
      </div>
      <div class="info-box">
        <h3>ENTREPRISE</h3>
        <p style="font-weight:600;"><?= htmlspecialchars($facture['entreprise_nom'] ?? '—') ?></p>
        <?php if (!empty($facture['entreprise_adresse'])): ?>
        <p><?= htmlspecialchars($facture['entreprise_adresse']) ?></p>
        <?php endif; ?>
        <p>P&eacute;riode: <?= fmtD($dateDebut) ?> - <?= fmtD($dateFin) ?></p>
      </div>
    </div>

    <hr class="sep">

    <!-- Detail table -->
    <div class="section-title">D&Eacute;TAIL DES PRESTATIONS (<?= $nbAdherents ?> adh&eacute;rent<?= $nbAdherents > 1 ? 's' : '' ?>)</div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Date soins</th>
          <th>Adh&eacute;rent</th>
          <th>Matricule</th>
          <th>Type acte</th>
          <th class="num">Montant</th>
          <th class="num">Part IPM</th>
          <th class="num">Part Adh.</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($prestations)): ?>
        <tr>
          <td colspan="7" style="text-align:center;color:#888;padding:20px;">
            Aucune prestation trouv&eacute;e pour cette p&eacute;riode
          </td>
        </tr>
        <?php else: ?>
          <?php foreach ($prestations as $p): ?>
          <tr>
            <td><?= fmtD($p['date_soins']) ?></td>
            <td><?= htmlspecialchars($p['adherent_nom']) ?></td>
            <td><?= htmlspecialchars($p['matricule'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['type_acte']) ?></td>
            <td class="num"><?= fmtM((int)$p['montant_total']) ?></td>
            <td class="num"><?= fmtM((int)$p['part_ipm']) ?></td>
            <td class="num"><?= fmtM((int)$p['part_adherent']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="font-weight:700;">TOTAL</td>
          <td class="num"><?= fmtM($totalMontant) ?></td>
          <td class="num"><?= fmtM($totalPartIpm) ?></td>
          <td class="num"><?= fmtM($totalPartAdherent) ?></td>
        </tr>
      </tfoot>
    </table>

    <!-- Summary box -->
    <table class="summary-box">
      <tr>
        <td>Montant total prestations:</td>
        <td class="amount"><?= fmtM($totalMontant) ?> FCFA</td>
      </tr>
      <tr>
        <td>Part prise en charge (IPM):</td>
        <td class="amount"><?= fmtM($totalPartIpm) ?> FCFA</td>
      </tr>
      <tr>
        <td>Montant d&eacute;j&agrave; r&eacute;gl&eacute;:</td>
        <td class="amount"><?= fmtM($montantPaye) ?> FCFA</td>
      </tr>
      <tr class="total-row">
        <td>NET &Agrave; PAYER:</td>
        <td class="amount"><?= fmtM($netAPayer) ?> FCFA</td>
      </tr>
    </table>

    <!-- Signatures -->
    <div class="signatures">
      <div class="sig-block">
        <div class="sig-label">Signature &amp; cachet IPM:</div>
        <div class="sig-line"></div>
      </div>
      <div class="sig-block">
        <div class="sig-label">Signature &amp; cachet Entreprise:</div>
        <div class="sig-line"></div>
      </div>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
      I'KARANG&#203; - Gestion Assurance &amp; Mutuelle<br>
      Document g&eacute;n&eacute;r&eacute; le <?= $now ?> &mdash; <?= $nbPrestations ?> prestation(s), <?= $nbAdherents ?> adh&eacute;rent(s)
    </div>

  </div>

</body>
</html>
<?php exit; ?>
