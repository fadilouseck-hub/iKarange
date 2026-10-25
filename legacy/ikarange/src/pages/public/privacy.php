<?php

/**
 * Privacy policy of the mobile apps (iOS + Android), public. The facts below are written from the implementation
 * (apps in ios/ and android/, mobile API in src/api/mobile/): keep them in sync when data flows change.
 * Operator-specific details come from config/public_legal.php.
 */

require_once __DIR__ . '/legal.php';

$tenant = legalTenant($legalTenantId ?? 'assurplus');
$lang = legalLang();
$T = fn(string $fr, string $en): string => $lang === 'en' ? $en : $fr;
$v = fn(string $field): string => legalValue($tenant, $field, $lang);
$app = e($tenant['appName']);
$path = '/' . $tenant['id'] . '/confidentialite';

// Countries served and their data-protection law / authority. Declaration references come from the operator config.
$references = (array)($tenant['regulatorReferences'] ?? []);
if (empty($references['sn']) && !empty($tenant['cdpReference'])) $references['sn'] = $tenant['cdpReference'];
$reference = fn(string $code): string => trim((string)($references[$code] ?? '')) !== ''
    ? e(trim((string)$references[$code]))
    : '<mark>' . ($lang === 'en' ? '[to be completed]' : '[à compléter]') . '</mark>';
$regulators = [
    'sn' => ['country' => $T('Sénégal', 'Senegal'),
        'law' => $T('loi n° 2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel', 'Law No. 2008-12 of 25 January 2008 on the protection of personal data'),
        'authority' => $T('la Commission de Protection des Données Personnelles (CDP)', 'the Commission de Protection des Données Personnelles (CDP)'),
        'reference' => $reference('sn')],
    'ml' => ['country' => 'Mali',
        'law' => $T('loi n° 2013-015 du 21 mai 2013 portant protection des données à caractère personnel', 'Law No. 2013-015 of 21 May 2013 on the protection of personal data'),
        'authority' => $T("l'Autorité de Protection des Données à caractère Personnel (APDP)", "the Autorité de Protection des Données à caractère Personnel (APDP)"),
        'reference' => $reference('ml')],
    'mr' => ['country' => 'Mauritanie',
        'law' => $T('loi n° 2017-020 du 22 juillet 2017 relative à la protection des données à caractère personnel', 'Law No. 2017-020 of 22 July 2017 on the protection of personal data'),
        'authority' => $T("l'Autorité de Protection des Données à caractère personnel (APD)", "the Autorité de Protection des Données à caractère personnel (APD)"),
        'reference' => $reference('mr')],
];
if ($lang === 'en') $regulators['mr']['country'] = 'Mauritania';

legalHeader($tenant, $T('Politique de confidentialité', 'Privacy policy'), $lang, $path);
?>
  <h1><?= $T('Politique de confidentialité', 'Privacy policy') ?></h1>
  <p class="updated"><?= $T('Application', 'App') ?> <?= $app ?> (iOS, Android) · <?= $T('Mise à jour', 'Updated') ?> <?= e($tenant['lastUpdated']) ?></p>

  <div class="card">
    <p><?= $T(
        "Cette politique explique quelles données l'application $app traite, pourquoi, avec qui elles sont partagées et comment exercer vos droits. L'application permet aux assurés et à leurs ayants droit de consulter leur contrat, présenter leur carte tiers-payant, déclarer des sinistres, gérer leur dossier médical et trouver un prestataire du réseau.",
        "This policy explains which data the $app app processes, why, who it is shared with and how to exercise your rights. The app lets insured members and their dependants view their contract, show their direct-billing card, submit claims, manage their medical records and find a network provider.") ?></p>
  </div>

  <h2><?= $T('1. Responsable du traitement', '1. Data controller') ?></h2>
  <p><?= $v('controller') ?><br><?= $v('address') ?><br>
    <?= $T('Contact données personnelles', 'Privacy contact') ?> : <?= $v('privacyEmail') ?></p>
  <p><?= $T(
      "L'application est proposée au Sénégal, au Mali et en Mauritanie. Selon votre pays de résidence, les traitements sont soumis à :",
      'The app is offered in Senegal, Mali and Mauritania. Depending on your country of residence, processing is governed by:') ?></p>
  <ul>
    <?php foreach ($regulators as $code => $r): ?>
      <li><strong><?= $r['country'] ?></strong> — <?= $r['law'] ?>, <?= $T('sous le contrôle de', 'supervised by') ?> <?= $r['authority'] ?>.
        <?= $T('Référence de déclaration :', 'Declaration reference:') ?> <?= $r['reference'] ?></li>
    <?php endforeach; ?>
  </ul>

  <h2><?= $T('2. Données traitées', '2. Data we process') ?></h2>
  <ul>
    <li><strong><?= $T('Identité et contact', 'Identity and contact') ?></strong> : <?= $T(
        "nom, prénom, date de naissance, sexe, numéro de téléphone, e-mail, adresse, ville, identifiant de connexion.",
        "first and last name, date of birth, gender, phone number, email, address, city, sign-in username.") ?></li>
    <li><strong><?= $T('Contrat', 'Contract') ?></strong> : <?= $T(
        "numéro d'assuré et de contrat, formule, garanties, taux de couverture, plafonds et consommation, ayants droit rattachés.",
        "member and policy numbers, plan, guarantees, coverage rates, limits and consumption, linked dependants.") ?></li>
    <li><strong><?= $T('Santé', 'Health') ?></strong> : <?= $T(
        "sinistres déclarés (type de soins, prestataire, montants, actes et médicaments), justificatifs et factures photographiés ou importés, documents que vous déposez dans votre dossier médical (ordonnances, analyses, radios, vaccins), réponses au questionnaire de santé lors d'une souscription.",
        "claims you submit (type of care, provider, amounts, procedures and medicines), receipts and invoices you photograph or import, documents you store in your medical records (prescriptions, test results, X-rays, vaccinations), answers to the health questionnaire when subscribing.") ?></li>
    <li><strong><?= $T('Paiements', 'Payments') ?></strong> : <?= $T(
        "historique des cotisations (montant, date, moyen, référence, statut). Les numéros de carte et codes Wave / Orange Money sont saisis chez l'opérateur de paiement : l'application ne les reçoit pas.",
        "premium history (amount, date, method, reference, status). Card numbers and Wave / Orange Money codes are entered with the payment provider: the app never receives them.") ?></li>
    <li><strong><?= $T('Localisation', 'Location') ?></strong> : <?= $T(
        "uniquement si vous touchez « Autour de moi » dans le réseau de soins, et après votre autorisation : la position est envoyée avec la recherche pour trier les prestataires par distance. Elle n'est pas enregistrée dans votre dossier.",
        "only if you tap “Near me” in the care network and grant permission: your position is sent with the search to sort providers by distance. It is not stored in your file.") ?></li>
    <li><strong><?= $T('Messages', 'Messages') ?></strong> : <?= $T(
        "les avis et signalements que vous envoyez depuis l'application, avec la version de l'application, du système et le modèle de l'appareil ; les questions posées à l'assistant (voir section 4).",
        "feedback and problem reports you send from the app, with the app version, system version and device model; questions you ask the assistant (see section 4).") ?></li>
    <li><strong><?= $T('Sécurité', 'Security') ?></strong> : <?= $T(
        "jetons de session, journal des tentatives de connexion (adresse IP) pour bloquer les attaques.",
        "session tokens, log of sign-in attempts (IP address) to block attacks.") ?></li>
  </ul>
  <p><?= $T(
      "L'application ne contient ni publicité, ni outil de mesure d'audience, ni traceur publicitaire, et ne lit pas l'identifiant publicitaire de l'appareil.",
      "The app contains no advertising, no audience analytics and no ad trackers, and does not read the device advertising ID.") ?></p>

  <h2><?= $T('3. Finalités et bases légales', '3. Purposes and legal bases') ?></h2>
  <ul>
    <li><?= $T("Gérer votre contrat, votre carte tiers-payant, vos remboursements et prises en charge : exécution du contrat d'assurance.", 'Managing your contract, direct-billing card, reimbursements and approvals: performance of the insurance contract.') ?></li>
    <li><?= $T('Traiter les données de santé nécessaires aux remboursements : votre consentement et les obligations de l\'assureur.', 'Processing the health data needed for reimbursements: your consent and the insurer\'s obligations.') ?></li>
    <li><?= $T('Conserver les pièces comptables et contractuelles : obligations légales.', 'Keeping accounting and contract records: legal obligations.') ?></li>
    <li><?= $T("Sécuriser les comptes et répondre à vos messages : intérêt légitime de l'opérateur.", "Securing accounts and answering your messages: the operator's legitimate interest.") ?></li>
  </ul>

  <h2><?= $T('4. Destinataires et sous-traitants', '4. Recipients and processors') ?></h2>
  <ul>
    <li><?= $T("Votre assureur et votre gestionnaire, et les prestataires de santé du réseau pour la prise en charge (le QR code de la carte contient un jeton temporaire, sans donnée personnelle lisible).", 'Your insurer and account manager, and network healthcare providers for direct billing (the card QR code holds a temporary token with no readable personal data).') ?></li>
    <li><?= $T('Hébergement des serveurs et des fichiers : OVHcloud (France).', 'Server and file hosting: OVHcloud (France).') ?></li>
    <li><?= $T(
        "Assistant (facultatif) : vos questions, avec le nom de votre formule, de votre entreprise, de votre assureur et vos taux de couverture, sont transmises à Anthropic (États-Unis) pour générer la réponse. Votre nom, vos sinistres, vos documents et vos paiements ne sont pas transmis. N'indiquez pas d'informations médicales dans l'assistant.",
        'Assistant (optional): your questions, with the name of your plan, employer and insurer and your coverage rates, are sent to Anthropic (United States) to generate the answer. Your name, claims, documents and payments are not sent. Do not share medical information with the assistant.') ?></li>
    <li><?= $T("Paiement : Wave, Orange Money ou l'opérateur de carte bancaire, lorsque vous payez une cotisation.", 'Payment: Wave, Orange Money or the card payment provider, when you pay a premium.') ?></li>
    <li><?= $T(
        "Cartes : sur Android, les fonds de carte proviennent d'OpenStreetMap (votre adresse IP et la zone affichée sont transmises au serveur de tuiles) ; sur iPhone, d'Apple Plans. « Itinéraire » ouvre l'application de navigation de votre choix.",
        'Maps: on Android, map tiles come from OpenStreetMap (your IP address and the area shown are sent to the tile server); on iPhone, from Apple Maps. “Directions” opens the navigation app of your choice.') ?></li>
    <li><?= $T(
        "Numérisation des justificatifs : sur Android, le scanner de documents des services Google Play ; sur iPhone, celui d'Apple. Le traitement de l'image a lieu sur l'appareil.",
        "Document scanning: on Android, the Google Play services document scanner; on iPhone, Apple's. The image is processed on the device.") ?></li>
  </ul>
  <p><?= $T(
      "Certaines données sont donc hébergées ou traitées hors de votre pays de résidence : en France (hébergement) et aux États-Unis (assistant, s'il est utilisé). Ces transferts sont encadrés par les engagements contractuels de ces prestataires et limités à ce qui est nécessaire au service.",
      'Some data is therefore hosted or processed outside your country of residence: in France (hosting) and in the United States (the assistant, if used). These transfers are covered by the providers\' contractual commitments and limited to what the service needs.') ?></p>
  <p><?= $T('Vos données ne sont ni vendues ni louées.', 'Your data is never sold or rented.') ?></p>

  <h2><?= $T('5. Durées de conservation', '5. Retention') ?></h2>
  <p><?= $T(
      "Les données du contrat, des sinistres et des paiements sont conservées pendant la durée du contrat puis pendant",
      'Contract, claim and payment data are kept for the duration of the contract and then for') ?> <?= $v('retentionPeriod') ?> <?= $T(
      "pour répondre aux obligations légales et réglementaires de l'assurance. Les documents du dossier médical sont supprimés dès que vous les effacez dans l'application. Les tentatives de connexion ne servent qu'à bloquer les attaques et sont effacées automatiquement.",
      'to meet the legal and regulatory obligations of insurance. Medical-records documents are deleted as soon as you delete them in the app. Sign-in attempts are only used to block attacks and are deleted automatically.') ?></p>

  <h2><?= $T('6. Sécurité', '6. Security') ?></h2>
  <p><?= $T(
      "Les échanges sont chiffrés (HTTPS). Les documents du dossier médical sont chiffrés sur le serveur (AES-256). Sur le téléphone, les jetons de session sont protégés par le trousseau (iPhone) ou le Keystore (Android), et les fichiers téléchargés restent dans l'espace privé de l'application, exclus des sauvegardes. Le contenu est masqué dans le sélecteur d'applications et un verrouillage biométrique est disponible.",
      'All exchanges are encrypted (HTTPS). Medical-records documents are encrypted on the server (AES-256). On the phone, session tokens are protected by the Keychain (iPhone) or Keystore (Android), and downloaded files stay in the app\'s private storage, excluded from backups. Content is hidden in the app switcher and a biometric lock is available.') ?></p>

  <h2><?= $T('7. Vos droits', '7. Your rights') ?></h2>
  <p><?= $T(
      "Vous pouvez accéder à vos données, les faire rectifier, vous opposer à un traitement ou demander la suppression de votre compte en écrivant à",
      'You can access your data, have it corrected, object to processing or request deletion of your account by writing to') ?> <?= $v('privacyEmail') ?>.
    <?= $T('Vous pouvez aussi demander la suppression de votre compte', 'You can also request account deletion') ?>
    <a href="/<?= e($tenant['id']) ?>/suppression-compte<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $T('sur cette page', 'on this page') ?></a>
    <?= $T("ou dans l'application (Profil › Supprimer mon compte).", 'or in the app (Profile › Delete my account).') ?>
    <?= $T(
        "Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir l'autorité de protection des données de votre pays : CDP au Sénégal (www.cdp.sn), APDP au Mali (www.apdp.ml), APD en Mauritanie (www.apd.mr).",
        'If you believe your rights are not respected, you may contact the data-protection authority of your country: CDP in Senegal (www.cdp.sn), APDP in Mali (www.apdp.ml), APD in Mauritania (www.apd.mr).') ?></p>

  <h2><?= $T('8. Mineurs', '8. Children') ?></h2>
  <p><?= $T(
      "L'application est destinée aux assurés majeurs. Les données des enfants ayants droit sont gérées par l'assuré principal.",
      'The app is intended for adult members. Data of dependent children is managed by the main policyholder.') ?></p>

  <h2><?= $T('9. Modifications', '9. Changes') ?></h2>
  <p><?= $T(
      "Toute modification importante de cette politique vous sera signalée dans l'application.",
      'Any significant change to this policy will be announced in the app.') ?></p>
<?php
legalFooter($tenant, $lang);
