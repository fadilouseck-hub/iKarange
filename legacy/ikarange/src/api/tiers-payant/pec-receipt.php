<?php

/**
 * GET /api/tiers-payant/pec-receipt/{id}
 * Generate a printable PEC receipt for signature (adherent + prestataire).
 * Returns HTML that auto-triggers print dialog.
 */

// Allow both prestataire and admin access
$presta = prestaUser();
$admin = authUser();
if (!$presta && !$admin) {
    http_response_code(401);
    echo 'Non autorise';
    exit;
}

$pecId = (int)($_GET['id'] ?? 0);
if (!$pecId) {
    http_response_code(400);
    echo 'ID requis';
    exit;
}

$oid = $presta ? prestaOrgId() : orgId();

$stmt = db()->prepare('
    SELECT pec.*,
           a.nom as adherent_nom, a.prenom as adherent_prenom, a.matricule, a.sexe,
           a.date_naissance, a.telephone as adherent_tel, a.categorie,
           e.raison_sociale as entreprise_nom,
           p.nom as prestataire_nom, p.type as prestataire_type, p.telephone as prestataire_tel,
           p.adresse as prestataire_adresse
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    LEFT JOIN prestataires p ON p.id = pec.prestataire_id
    WHERE pec.id = ? AND pec.org_id = ?
');
$stmt->execute([$pecId, $oid]);
$pec = $stmt->fetch();

if (!$pec) {
    http_response_code(404);
    echo 'PEC introuvable';
    exit;
}

// Get org name
$stmt = db()->prepare('SELECT name FROM organizations WHERE id = ?');
$stmt->execute([$oid]);
$orgName = $stmt->fetchColumn() ?: "I'KARANGE";

$typeActeLabels = [
    'consultation' => 'Consultation', 'analyse' => 'Analyse', 'pharmacie' => 'Pharmacie',
    'hospitalisation' => 'Hospitalisation', 'imagerie' => 'Imagerie', 'dentaire' => 'Dentaire',
    'chirurgie' => 'Chirurgie', 'optique' => 'Optique', 'maternite' => 'Maternité', 'autre' => 'Autre'
];
$typeLabel = $typeActeLabels[$pec['type_acte']] ?? $pec['type_acte'];

$statutLabels = [
    'en_attente' => 'En attente', 'approuvee' => 'Approuvée', 'reglee' => 'Réglée',
    'rejetee' => 'Rejetée', 'facturee' => 'Facturée'
];
$statutLabel = $statutLabels[$pec['statut']] ?? $pec['statut'];

$montantTotal = number_format($pec['montant_total'], 0, ',', ' ');
$partIpm = number_format($pec['part_ipm'], 0, ',', ' ');
$partAdherent = number_format($pec['part_adherent'], 0, ',', ' ');
$dateSoins = date('d/m/Y', strtotime($pec['date_soins']));
$dateCreation = date('d/m/Y H:i', strtotime($pec['created_at']));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>PEC <?= e($pec['numero']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 20px; }
  @media print { body { padding: 10px; } @page { size: A4; margin: 15mm; } }

  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a3a3a; padding-bottom: 12px; margin-bottom: 15px; }
  .header .logo { font-size: 20px; font-weight: 700; color: #2dd4a8; }
  .header .org { font-size: 14px; font-weight: 600; color: #1a3a3a; }
  .header .subtitle { font-size: 10px; color: #888; }
  .header .right { text-align: right; }
  .header .pec-num { font-size: 16px; font-weight: 700; color: #1a3a3a; }
  .header .pec-date { font-size: 10px; color: #888; }

  h2 { font-size: 14px; color: #1a3a3a; margin: 15px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e0e0e0; }

  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; margin-bottom: 12px; font-size: 11px; }
  .info-grid .label { color: #888; }
  .info-grid .value { font-weight: 600; }

  .amounts { border: 2px solid #1a3a3a; border-radius: 8px; padding: 15px; margin: 15px 0; }
  .amounts table { width: 100%; border-collapse: collapse; }
  .amounts th { text-align: left; font-size: 11px; color: #888; padding: 4px 8px; border-bottom: 1px solid #e0e0e0; }
  .amounts td { padding: 6px 8px; font-size: 13px; }
  .amounts .total { font-weight: 700; font-size: 15px; }
  .amounts .ipm { color: #2dd4a8; font-weight: 700; font-size: 15px; }

  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; }
  .sig-box { border: 1px solid #d0d0d0; border-radius: 8px; padding: 15px; min-height: 120px; }
  .sig-box .sig-title { font-weight: 700; font-size: 11px; color: #1a3a3a; margin-bottom: 5px; text-transform: uppercase; }
  .sig-box .sig-name { font-size: 11px; color: #555; margin-bottom: 30px; }
  .sig-box .sig-line { border-top: 1px solid #999; padding-top: 5px; font-size: 9px; color: #888; text-align: center; }

  .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #e0e0e0; padding-top: 8px; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
  .badge-en_attente { background: #fff3e0; color: #e65100; }
  .badge-approuvee { background: #e8f5e9; color: #2e7d32; }
  .no-print { margin-bottom: 15px; text-align: center; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="padding:8px 24px;background:#2dd4a8;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">
    Imprimer / Sauvegarder PDF
  </button>
</div>

<div class="header">
  <div>
    <div class="logo">I'KARANG&Eacute;</div>
    <div class="org"><?= e($orgName) ?></div>
    <div class="subtitle">Gestion Assurance &amp; Mutuelle</div>
  </div>
  <div class="right">
    <div class="pec-num"><?= e($pec['numero']) ?></div>
    <div class="pec-date">Cr&eacute;&eacute; le <?= $dateCreation ?></div>
    <div style="margin-top:4px;"><span class="badge badge-<?= e($pec['statut']) ?>"><?= e($statutLabel) ?></span></div>
  </div>
</div>

<h2>Prise en Charge</h2>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div>
    <h2 style="font-size:12px;">Adh&eacute;rent</h2>
    <div class="info-grid">
      <span class="label">Nom</span><span class="value"><?= e($pec['adherent_nom'] . ' ' . $pec['adherent_prenom']) ?></span>
      <span class="label">Matricule</span><span class="value"><?= e($pec['matricule']) ?></span>
      <span class="label">Entreprise</span><span class="value"><?= e($pec['entreprise_nom'] ?? '') ?></span>
      <span class="label">Cat&eacute;gorie</span><span class="value"><?= e($pec['categorie'] ?? '') ?></span>
      <span class="label">T&eacute;l&eacute;phone</span><span class="value"><?= e($pec['adherent_tel'] ?? '') ?></span>
    </div>
  </div>
  <div>
    <h2 style="font-size:12px;">Prestataire</h2>
    <div class="info-grid">
      <span class="label">Nom</span><span class="value"><?= e($pec['prestataire_nom'] ?? '') ?></span>
      <span class="label">Type</span><span class="value"><?= e(ucfirst($pec['prestataire_type'] ?? '')) ?></span>
      <span class="label">Adresse</span><span class="value"><?= e($pec['prestataire_adresse'] ?? '') ?></span>
      <span class="label">T&eacute;l&eacute;phone</span><span class="value"><?= e($pec['prestataire_tel'] ?? '') ?></span>
    </div>
  </div>
</div>

<div class="amounts">
  <table>
    <thead>
      <tr>
        <th>Type d'acte</th>
        <th>Date des soins</th>
        <th>Motif</th>
        <th style="text-align:right;">Montant total</th>
        <th style="text-align:right;">Taux</th>
        <th style="text-align:right;">Part IPM</th>
        <th style="text-align:right;">Part Adh&eacute;rent</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="total"><?= e($typeLabel) ?></td>
        <td><?= $dateSoins ?></td>
        <td><?= e($pec['motif'] ?? '') ?></td>
        <td style="text-align:right;" class="total"><?= $montantTotal ?> F</td>
        <td style="text-align:right;"><?= $pec['taux_couverture'] ?>%</td>
        <td style="text-align:right;" class="ipm"><?= $partIpm ?> F</td>
        <td style="text-align:right;"><?= $partAdherent ?> F</td>
      </tr>
    </tbody>
  </table>
</div>

<?php if ($pec['observations']): ?>
<div style="font-size:11px;color:#555;margin-bottom:10px;">
  <strong>Observations :</strong> <?= e($pec['observations']) ?>
</div>
<?php endif; ?>

<div class="signatures">
  <div class="sig-box">
    <div class="sig-title">Signature de l'adh&eacute;rent</div>
    <div class="sig-name"><?= e($pec['adherent_nom'] . ' ' . $pec['adherent_prenom']) ?></div>
    <div class="sig-line">Date et signature</div>
  </div>
  <div class="sig-box">
    <div class="sig-title">Cachet et signature du prestataire</div>
    <div class="sig-name"><?= e($pec['prestataire_nom'] ?? '') ?></div>
    <div class="sig-line">Date, cachet et signature</div>
  </div>
</div>

<div class="footer">
  I'KARANG&Eacute; &mdash; <?= e($orgName) ?> | Gestion Assurance &amp; Mutuelle | Powered by MCE Group | Document g&eacute;n&eacute;r&eacute; le <?= date('d/m/Y \a\\ H:i') ?>
</div>

<script>window.onload = function() { window.print(); };</script>
</body>
</html>
