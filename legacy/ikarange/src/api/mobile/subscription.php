<?php

/**
 * Online subscription for the insured apps — TEST MODE.
 *
 * The purchase journey (formulas → health questionnaire → quote → contract → payment) is implemented end to end
 * so it can be reviewed in the apps, but nothing in this file is a real business rule or a real payment:
 *  - the formulas, guarantees, prices, surcharges and eligibility rules are PLACEHOLDERS, to be replaced by the
 *    insurer's real ones (they are labelled "fictif" / "test" wherever the member sees them);
 *  - the payment page is hosted here and only simulates Wave / Orange Money / card. No provider is called and no
 *    money moves. Real providers plug in at subCreatePayment() (checkout URL) and subCheckoutSubmit() (webhook).
 *
 * Only members listed in mobile_online_subscribers get this journey (see database/create_subscription_tester.php);
 * members of company plans are unaffected. Tables: database/010_online_subscription.sql.
 */

const SUB_QUOTE_TTL = 7 * 86400;
const SUB_PAYMENT_TTL = 1800;
const SUB_CONDITIONS_VERSION = 'CP-TEST-1';
const SUB_QUESTIONNAIRE_VERSION = 'HQ-TEST-1';

// ── Who ─────────────────────────────────────────────────

/** True for members who subscribe online (individual contract) instead of belonging to a company plan. */
function subIsOnline(array $a): bool
{
    static $cache = [];
    $id = (int)$a['id'];
    if (!isset($cache[$id])) {
        try {
            $stmt = db()->prepare('SELECT 1 FROM mobile_online_subscribers WHERE adherent_id = ?');
            $stmt->execute([$id]);
            $cache[$id] = (bool)$stmt->fetchColumn();
        } catch (PDOException) {
            $cache[$id] = false; // tables not installed yet: everyone stays on the company-plan behaviour
        }
    }
    return $cache[$id];
}

function subActivePolicy(array $a): ?array
{
    $stmt = db()->prepare("SELECT * FROM mobile_policies WHERE adherent_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$a['id']]);
    return $stmt->fetch() ?: null;
}

function subPermissions(array $a): array
{
    return subActivePolicy($a)
        ? ['policy.view', 'card.view', 'claims.view', 'claims.create', 'vault.view', 'vault.manage', 'payments.view']
        : ['subscription.create', 'payments.create', 'payments.view'];
}

function subRequireOnline(array $a): void
{
    if (!subIsOnline($a)) mNotAvailable();
}

// ── Placeholder catalogue ───────────────────────────────

function subProducts(): array
{
    $fictif = fn(int $amount) => t('Plafond fictif : ', 'Placeholder limit: ') . number_format($amount, 0, ',', ' ') . ' FCFA';
    $common = [
        'exclusions' => [t('Exclusions à définir par l\'assureur (formule de test).', 'Exclusions to be defined by the insurer (test plan).')],
        'waitingPeriods' => [t('Délais de carence à définir par l\'assureur (formule de test).', 'Waiting periods to be defined by the insurer (test plan).')],
        'deductibleLabel' => t('Franchise à définir (formule de test)', 'Deductible to be defined (test plan)'),
    ];
    return [
        [
            'id' => 'prd_essentiel', 'name' => 'Essentiel',
            'tagline' => t('Les soins courants, l\'essentiel pour bien démarrer', 'Everyday care, the essentials to get started'),
            'description' => t('Formule de test : garanties et tarifs fictifs, en attente des valeurs de l\'assureur.', 'Test plan: placeholder guarantees and prices, pending the insurer\'s values.'),
            'premiumFrom' => 95000, 'highlight' => false, 'annualLimit' => 1000000,
            'guarantees' => [
                ['code' => 'consult', 'label' => t('Consultations', 'Consultations'), 'limitLabel' => $fictif(150000), 'rateLabel' => null, 'description' => null],
                ['code' => 'pharma', 'label' => t('Pharmacie', 'Pharmacy'), 'limitLabel' => $fictif(300000), 'rateLabel' => null, 'description' => null],
                ['code' => 'lab', 'label' => t('Analyses', 'Lab tests'), 'limitLabel' => $fictif(150000), 'rateLabel' => null, 'description' => null],
            ],
            'coverageRates' => [70, 80],
            'territorialities' => [['code' => 'SN', 'label' => t('Sénégal', 'Senegal')]],
            'maxDependents' => 5,
        ] + $common,
        [
            'id' => 'prd_serenite', 'name' => 'Sérénité',
            'tagline' => t('Une couverture complète pour toute la famille', 'Full cover for the whole family'),
            'description' => t('Formule de test : garanties et tarifs fictifs, en attente des valeurs de l\'assureur.', 'Test plan: placeholder guarantees and prices, pending the insurer\'s values.'),
            'premiumFrom' => 180000, 'highlight' => true, 'annualLimit' => 3000000,
            'guarantees' => [
                ['code' => 'consult', 'label' => t('Consultations', 'Consultations'), 'limitLabel' => $fictif(300000), 'rateLabel' => null, 'description' => null],
                ['code' => 'pharma', 'label' => t('Pharmacie', 'Pharmacy'), 'limitLabel' => $fictif(600000), 'rateLabel' => null, 'description' => null],
                ['code' => 'lab', 'label' => t('Analyses et imagerie', 'Lab tests and imaging'), 'limitLabel' => $fictif(400000), 'rateLabel' => null, 'description' => null],
                ['code' => 'hospital', 'label' => t('Hospitalisation', 'Hospital stay'), 'limitLabel' => $fictif(1500000), 'rateLabel' => null, 'description' => null],
            ],
            'coverageRates' => [70, 80, 90, 100],
            'territorialities' => [['code' => 'SN', 'label' => t('Sénégal', 'Senegal')], ['code' => 'CIMA', 'label' => t('Zone CIMA', 'CIMA zone')]],
            'maxDependents' => 8,
        ] + $common,
    ];
}

function subProduct(string $id): ?array
{
    foreach (subProducts() as $product) {
        if ($product['id'] === $id) return $product;
    }
    return null;
}

/** Products as the apps expect them (internal keys removed). */
function subPublicProducts(): array
{
    return array_map(function (array $p) {
        unset($p['annualLimit']);
        return $p;
    }, subProducts());
}

function subQuestionnaire(): array
{
    $q = fn(string $id, string $label, ?string $help, bool $required, ?string $dependsOn = null, string $kind = 'boolean', ?array $options = null) =>
        ['id' => $id, 'label' => $label, 'help' => $help, 'kind' => $kind, 'options' => $options, 'required' => $required, 'dependsOn' => $dependsOn];
    return [
        'version' => SUB_QUESTIONNAIRE_VERSION,
        'questions' => [
            $q('q_chronic', t('Suivez-vous un traitement pour une maladie chronique ?', 'Are you being treated for a chronic condition?'), t('Diabète, hypertension, asthme…', 'Diabetes, hypertension, asthma…'), true),
            $q('q_chronic_detail', t('Laquelle ?', 'Which one?'), null, true, 'q_chronic', 'single_choice', [
                ['code' => 'diabetes', 'label' => t('Diabète', 'Diabetes')], ['code' => 'hypertension', 'label' => t('Hypertension', 'Hypertension')],
                ['code' => 'asthma', 'label' => t('Asthme', 'Asthma')], ['code' => 'other', 'label' => t('Autre', 'Other')],
            ]),
            $q('q_hospital', t('Avez-vous été hospitalisé(e) au cours des 12 derniers mois ?', 'Have you been hospitalised in the last 12 months?'), null, true),
            $q('q_incurable', t("Êtes-vous atteint(e) d'une affection de longue durée non stabilisée ?", 'Do you have an unstabilised long-term condition?'), t("Réponse utilisée par l'assureur pour l'éligibilité.", 'Used by the insurer to assess eligibility.'), true),
            $q('q_pregnant', t('Êtes-vous enceinte ?', 'Are you pregnant?'), null, false),
        ],
    ];
}

// ── Quote (placeholder pricing) ─────────────────────────

function subAge(?string $day): ?int
{
    $date = $day ? DateTime::createFromFormat('!Y-m-d', $day) : false;
    return $date ? (int)$date->diff(new DateTime('today'))->y : null;
}

function subQuote(array $a, array $in): array
{
    $product = subProduct((string)($in['productId'] ?? ''));
    if (!$product) mError(422, 'invalid_product', t('Formule inconnue.', 'Unknown plan.'), ['productId' => t('Formule inconnue.', 'Unknown plan.')]);
    $rate = (int)($in['coverageRate'] ?? 0);
    $zone = (string)($in['territoriality'] ?? '');
    $age = subAge((string)($in['birthDate'] ?? ''));
    $dependents = array_values(array_filter((array)($in['dependents'] ?? []), 'is_array'));
    $errors = [];
    if (!in_array($rate, $product['coverageRates'], true)) $errors['coverageRate'] = t('Taux de couverture non proposé pour cette formule.', 'Coverage rate not offered for this plan.');
    if (!in_array($zone, array_column($product['territorialities'], 'code'), true)) $errors['territoriality'] = t('Territorialité non proposée pour cette formule.', 'Territory not offered for this plan.');
    if ($age === null || $age < 18) $errors['birthDate'] = t('Le souscripteur doit être majeur.', 'The policyholder must be an adult.');
    if (count($dependents) > $product['maxDependents']) $errors['dependents'] = t('Trop de bénéficiaires pour cette formule.', 'Too many dependants for this plan.');
    if ($errors) mError(422, 'invalid_quote', reset($errors), $errors);

    // Placeholder tariff: base price × coverage rate × territory × age band, rounded to 500 FCFA.
    $round = fn(float $value) => (int)(round($value / 500) * 500);
    $base = $product['premiumFrom'] * (1 + ($rate - 70) / 100 * 1.5) * ($zone === 'CIMA' ? 1.2 : 1);
    $ageFactor = $age < 30 ? 1.0 : ($age < 45 ? 1.15 : ($age < 60 ? 1.4 : 1.8));
    $perMember = [['label' => t('Assuré principal', 'Main policyholder'), 'amount' => $round($base * $ageFactor)]];
    $members = [];
    foreach ($dependents as $member) {
        $relation = in_array($member['relation'] ?? '', ['spouse', 'child', 'other'], true) ? $member['relation'] : 'other';
        $first = mb_substr(trim((string)($member['firstName'] ?? '')), 0, 80);
        $last = mb_substr(trim((string)($member['lastName'] ?? '')), 0, 80);
        $relationLabel = match ($relation) {
            'spouse' => t('Conjoint(e)', 'Spouse'),
            'child' => t('Enfant', 'Child'),
            default => t('Autre ayant droit', 'Other dependant'),
        };
        $perMember[] = ['label' => "$first ($relationLabel)", 'amount' => $round($base * ($relation === 'child' ? 0.35 : 0.8))];
        $members[] = ['fullName' => trim("$first $last"), 'relationLabel' => $relationLabel, 'birthDate' => mDay((string)($member['birthDate'] ?? ''))];
    }
    $basePremium = array_sum(array_column($perMember, 'amount'));

    $answers = [];
    foreach ((array)($in['answers'] ?? []) as $answer) {
        if (is_array($answer) && isset($answer['questionId'])) $answers[(string)$answer['questionId']] = (string)($answer['value'] ?? '');
    }
    $messages = [];
    $surcharges = [];
    $eligible = true;
    if (($answers['q_chronic'] ?? '') === 'true') {
        $surcharges[] = ['label' => t('Surprime santé (fictive)', 'Health surcharge (placeholder)'), 'amount' => $round($basePremium * 0.15)];
        $messages[] = ['level' => 'warning', 'text' => t("Une surprime s'applique selon vos réponses au questionnaire de santé.", 'A surcharge applies based on your health questionnaire answers.')];
    }
    if (($answers['q_incurable'] ?? '') === 'true') {
        $eligible = false;
        $messages[] = ['level' => 'error', 'text' => t('Selon vos réponses, une étude médicale est nécessaire avant toute souscription. Un conseiller vous contactera.', 'Based on your answers, a medical review is required before subscribing. An adviser will contact you.')];
    }
    if ($age > 70) {
        $eligible = false;
        $messages[] = ['level' => 'error', 'text' => t("L'âge limite de souscription en ligne est dépassé (règle fictive).", 'The age limit for online subscription is exceeded (placeholder rule).')];
    }
    if ($eligible && !$messages) $messages[] = ['level' => 'success', 'text' => t('Vous êtes éligible à cette formule.', 'You are eligible for this plan.')];
    $messages[] = ['level' => 'info', 'text' => t('Mode test : tarifs et règles fictifs, sans valeur contractuelle.', 'Test mode: placeholder prices and rules, with no contractual value.')];

    $fees = [['label' => t('Frais de dossier (fictifs)', 'Administration fee (placeholder)'), 'amount' => 5000]];
    $total = $basePremium + array_sum(array_column($surcharges, 'amount')) + array_sum(array_column($fees, 'amount'));
    $zoneLabel = '';
    foreach ($product['territorialities'] as $option) {
        if ($option['code'] === $zone) $zoneLabel = $option['label'];
    }
    $quote = [
        'eligible' => $eligible, 'messages' => $messages, 'basePremium' => $basePremium, 'surcharges' => $surcharges, 'fees' => $fees,
        'totalPremium' => $total, 'periodLabel' => t('par an', 'per year'), 'perMember' => $perMember,
        'validUntil' => gmdate('Y-m-d\TH:i:s\Z', time() + SUB_QUOTE_TTL), 'conditionsVersion' => SUB_CONDITIONS_VERSION,
        'specialConditions' => [
            t('Formule ', 'Plan ') . $product['name'] . t(', taux de couverture ', ', coverage rate ') . $rate . ' %.',
            t('Territorialité : ', 'Territory: ') . $zoneLabel . '.',
            t("Conditions particulières de test — le texte réel sera fourni par l'assureur.", "Test policy conditions — the real text will be provided by the insurer."),
        ],
    ];
    $snapshot = ['productId' => $product['id'], 'coverageRate' => $rate, 'territoriality' => $zoneLabel, 'members' => $members, 'quote' => $quote];
    db()->prepare('INSERT INTO mobile_quotes (adherent_id, snapshot, total, eligible, expires_at) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))')
        ->execute([$a['id'], json_encode($snapshot, JSON_UNESCAPED_UNICODE), $total, $eligible ? 1 : 0, time() + SUB_QUOTE_TTL]);
    return ['id' => 'qte_' . (int)db()->lastInsertId()] + $quote;
}

// ── Contract ────────────────────────────────────────────

function subCreatePolicy(array $a, array $in): array
{
    if (subActivePolicy($a)) mError(409, 'already_insured', t('Vous avez déjà un contrat actif.', 'You already have an active contract.'));
    $stmt = db()->prepare('SELECT *, expires_at < NOW() AS expired FROM mobile_quotes WHERE id = ? AND adherent_id = ?');
    $stmt->execute([(int)preg_replace('/\D+/', '', (string)($in['quoteId'] ?? '')), $a['id']]);
    $quote = $stmt->fetch();
    if (!$quote) mError(404, 'not_found', t('Devis introuvable.', 'Quote not found.'));
    if ($quote['expired']) mError(410, 'quote_expired', t('Ce devis a expiré. Il va être recalculé.', 'This quote has expired. It will be recalculated.'));
    if (!$quote['eligible']) mError(422, 'not_eligible', t("Ce devis ne permet pas la souscription en ligne.", 'This quote cannot be subscribed online.'));
    if (($in['acceptedConditionsVersion'] ?? '') !== SUB_CONDITIONS_VERSION) {
        mError(422, 'conditions_not_accepted', t('Vous devez accepter les conditions particulières.', 'You must accept the policy conditions.'));
    }
    $snapshot = json_decode($quote['snapshot'], true);
    $pdo = db();
    $pdo->beginTransaction();
    // One contract awaiting payment at a time: a new attempt replaces the previous one.
    $pdo->prepare("UPDATE mobile_policies SET status = 'cancelled' WHERE adherent_id = ? AND status = 'pending_payment'")->execute([$a['id']]);
    $pdo->prepare("INSERT INTO mobile_policies (adherent_id, quote_id, number, product_id, coverage_rate, total_premium, snapshot, status)
                   VALUES (?, ?, '', ?, ?, ?, ?, 'pending_payment')")
        ->execute([$a['id'], $quote['id'], $snapshot['productId'], $snapshot['coverageRate'], $quote['total'], $quote['snapshot']]);
    $id = (int)$pdo->lastInsertId();
    $number = sprintf('ASP-TEST-%s-%05d', date('Y'), $id);
    $pdo->prepare('UPDATE mobile_policies SET number = ? WHERE id = ?')->execute([$number, $id]);
    $pdo->commit();
    return ['id' => 'pol_' . $id, 'number' => $number, 'status' => mStatus('pending_payment', 'En attente de paiement', 'Awaiting payment'),
        'amountDue' => (int)$quote['total'], 'startDate' => date('Y-m-d')];
}

function subPolicySummary(array $policy): array
{
    $snapshot = json_decode($policy['snapshot'], true);
    $product = subProduct($policy['product_id']);
    return [
        'id' => 'pol_' . $policy['id'],
        'number' => $policy['number'],
        'productName' => t('Santé individuelle (test)', 'Individual health (test)'),
        'formulaName' => $product['name'] ?? $policy['product_id'],
        'startDate' => mDay($policy['start_date']) ?? date('Y-m-d'),
        'endDate' => mDay($policy['end_date']) ?? date('Y-m-d', strtotime('+1 year -1 day')),
        'status' => $policy['status'] === 'active' ? mStatus('active', 'Actif', 'Active') : mStatus('pending_payment', 'En attente de paiement', 'Awaiting payment'),
        'coverageRate' => (int)$policy['coverage_rate'],
        'territoriality' => $snapshot['territoriality'] ?? null,
    ];
}

function subPolicyDetail(array $a, array $policy): array
{
    $snapshot = json_decode($policy['snapshot'], true);
    $product = subProduct($policy['product_id']) ?? ['guarantees' => [], 'exclusions' => [], 'waitingPeriods' => [], 'deductibleLabel' => null];
    $members = [['id' => 'adh_' . $a['id'], 'fullName' => trim($a['prenom'] . ' ' . $a['nom']),
        'relationLabel' => t('Assuré(e) principal(e)', 'Main policyholder'), 'birthDate' => mDay($a['date_naissance'])]];
    foreach ($snapshot['members'] ?? [] as $index => $member) {
        $members[] = ['id' => 'dep_' . $policy['id'] . '_' . $index] + $member;
    }
    return [
        'summary' => subPolicySummary($policy),
        'insurerName' => $a['org_name'] ?: "I'KARANGE",
        'premiumLabel' => number_format((int)$policy['total_premium'], 0, ',', ' ') . ' FCFA ' . t('/ an (tarif de test)', '/ year (test price)'),
        'members' => $members,
        'guarantees' => $product['guarantees'],
        'exclusions' => $product['exclusions'], 'waitingPeriods' => $product['waitingPeriods'], 'deductibleLabel' => $product['deductibleLabel'],
        'renewal' => $policy['end_date'] ? [
            'renewalDate' => date('Y-m-d', strtotime($policy['end_date'] . ' +1 day')), 'tacitRenewal' => true, 'terminationDeadline' => null,
            'canRequestTermination' => false, 'info' => t('Contrat de test à tacite reconduction.', 'Test contract with tacit renewal.'),
        ] : null,
        'conditionsVersion' => SUB_CONDITIONS_VERSION, 'pendingTermination' => null,
    ];
}

function subPolicyFor(array $a, string $publicId): array
{
    $stmt = db()->prepare("SELECT * FROM mobile_policies WHERE id = ? AND adherent_id = ? AND status IN ('active', 'pending_payment')");
    $stmt->execute([(int)preg_replace('/\D+/', '', $publicId), $a['id']]);
    $policy = str_starts_with($publicId, 'pol_') ? $stmt->fetch() : false;
    if (!$policy) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
    return $policy;
}

function subConditionsLines(array $a, array $policy): array
{
    $detail = subPolicyDetail($a, $policy);
    $snapshot = json_decode($policy['snapshot'], true);
    $lines = [
        t('DOCUMENT DE TEST — SANS VALEUR CONTRACTUELLE', 'TEST DOCUMENT — NO CONTRACTUAL VALUE'), '',
        t('Contrat n° ', 'Contract no. ') . $policy['number'],
        t('Formule : ', 'Plan: ') . $detail['summary']['formulaName'] . ' — ' . $detail['summary']['coverageRate'] . ' %',
        t('Période : ', 'Period: ') . $detail['summary']['startDate'] . ' → ' . $detail['summary']['endDate'],
        t('Prime : ', 'Premium: ') . $detail['premiumLabel'], '',
        t('Assurés :', 'Insured members:'),
    ];
    foreach ($detail['members'] as $member) $lines[] = '  - ' . $member['fullName'] . ' (' . $member['relationLabel'] . ')';
    $lines[] = '';
    $lines[] = t('Garanties :', 'Guarantees:');
    foreach ($detail['guarantees'] as $guarantee) $lines[] = '  - ' . $guarantee['label'] . ' — ' . $guarantee['limitLabel'];
    $lines[] = '';
    foreach ($snapshot['quote']['specialConditions'] ?? [] as $condition) $lines[] = $condition;
    return $lines;
}

// ── Test payment ────────────────────────────────────────

function subPaymentMethods(): array
{
    $help = t('Mode test : page de paiement simulée, aucun débit.', 'Test mode: simulated payment page, nothing is charged.');
    return [
        ['code' => 'wave', 'label' => 'Wave', 'kind' => 'wave', 'enabled' => true, 'requiresPhone' => true, 'help' => $help],
        ['code' => 'orange_money', 'label' => 'Orange Money', 'kind' => 'orange_money', 'enabled' => true, 'requiresPhone' => true, 'help' => $help],
        ['code' => 'card', 'label' => t('Carte bancaire', 'Bank card'), 'kind' => 'card', 'enabled' => true, 'requiresPhone' => false, 'help' => $help],
    ];
}

function subBaseUrl(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
    return ($local ? 'http://' : 'https://') . $host . '/api/mobile/v1';
}

function subPaymentJson(array $p, ?string $token = null): array
{
    $labels = array_column(subPaymentMethods(), 'label', 'code');
    $status = match ($p['status']) {
        'succeeded' => mStatus('succeeded', 'Payé (test)', 'Paid (test)'),
        'failed' => mStatus('failed', 'Échoué', 'Failed'),
        'cancelled' => mStatus('cancelled', 'Annulé', 'Cancelled'),
        'expired' => mStatus('expired', 'Expiré', 'Expired'),
        default => mStatus('pending', 'En attente', 'Pending'),
    };
    return [
        'id' => 'pay_' . $p['id'],
        'reference' => $p['reference'],
        'amount' => (int)$p['amount'],
        'methodLabel' => ($labels[$p['method']] ?? $p['method']) . ' (test)',
        'status' => $status,
        'createdAt' => mIso($p['created_at']),
        'policyNumber' => $p['policy_number'] ?? null,
        'purposeLabel' => t('Souscription', 'Subscription'),
        // The page opens in the system browser, whose language may differ from the app's: carry the app language.
        'checkoutURL' => $token !== null ? subBaseUrl() . '/payments/pay_' . $p['id'] . '/checkout?lang=' . mLang() . '&token=' . $token : null,
        'appURL' => null,
    ];
}

function subCreatePayment(array $a, array $in): array
{
    $policy = subPolicyFor($a, (string)($in['policyId'] ?? ''));
    if ($policy['status'] !== 'pending_payment') mError(409, 'already_paid', t('Ce contrat est déjà payé.', 'This contract is already paid.'));
    $method = (string)($in['method'] ?? '');
    $methods = array_column(subPaymentMethods(), null, 'code');
    if (!isset($methods[$method])) mError(422, 'invalid_method', t('Moyen de paiement inconnu.', 'Unknown payment method.'), ['method' => t('Moyen de paiement inconnu.', 'Unknown payment method.')]);
    $phone = mPhone((string)($in['phone'] ?? ''));
    if ($methods[$method]['requiresPhone'] && !preg_match('/^\+221\d{9}$/', $phone)) {
        mError(422, 'invalid_phone', t('Numéro de téléphone invalide.', 'Invalid phone number.'), ['phone' => t('Numéro de téléphone invalide.', 'Invalid phone number.')]);
    }
    // Only the apps' own scheme may receive the return redirect.
    $returnUrl = (string)($in['returnURL'] ?? '');
    if (!preg_match('#^assurplus://payments/return$#', $returnUrl)) mError(422, 'invalid_return_url', t('Requête invalide.', 'Invalid request.'));

    $token = bin2hex(random_bytes(24));
    db()->prepare("UPDATE mobile_payments SET status = 'cancelled' WHERE policy_id = ? AND status = 'pending'")->execute([$policy['id']]);
    db()->prepare("INSERT INTO mobile_payments (adherent_id, policy_id, reference, amount, method, phone, return_url, token_hash, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')")
        ->execute([$a['id'], $policy['id'], generateNumero('TEST'), $policy['total_premium'], $method, $methods[$method]['requiresPhone'] ? $phone : null, $returnUrl, hash('sha256', $token)]);
    return subPaymentJson(subPayment((int)db()->lastInsertId(), (int)$a['id']), $token);
}

/** One payment with its contract number; pending payments expire after SUB_PAYMENT_TTL. */
function subPayment(int $id, ?int $adherentId = null): ?array
{
    db()->prepare("UPDATE mobile_payments SET status = 'expired' WHERE id = ? AND status = 'pending' AND created_at < (NOW() - INTERVAL " . SUB_PAYMENT_TTL . ' SECOND)')->execute([$id]);
    $stmt = db()->prepare('SELECT p.*, pol.number AS policy_number FROM mobile_payments p JOIN mobile_policies pol ON pol.id = p.policy_id
                           WHERE p.id = ?' . ($adherentId !== null ? ' AND p.adherent_id = ?' : ''));
    $stmt->execute($adherentId !== null ? [$id, $adherentId] : [$id]);
    return $stmt->fetch() ?: null;
}

function subPayments(array $a): array
{
    $stmt = db()->prepare("SELECT p.*, pol.number AS policy_number FROM mobile_payments p JOIN mobile_policies pol ON pol.id = p.policy_id
                           WHERE p.adherent_id = ? AND p.status IN ('succeeded', 'failed') ORDER BY p.id DESC LIMIT 50");
    $stmt->execute([$a['id']]);
    return array_map(fn($p) => subPaymentJson($p), $stmt->fetchAll());
}

/** The payment behind a checkout link (no bearer token: the link itself carries a one-time secret). */
function subCheckoutPayment(string $publicId, string $token): array
{
    if (in_array($_GET['lang'] ?? '', ['fr', 'en'], true)) $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $_GET['lang'];
    $payment = str_starts_with($publicId, 'pay_') && $token !== '' ? subPayment((int)substr($publicId, 4)) : null;
    if (!$payment || !hash_equals($payment['token_hash'], hash('sha256', $token))) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo t('Lien de paiement invalide.', 'Invalid payment link.');
        exit;
    }
    return $payment;
}

function subCheckoutPage(string $publicId): never
{
    $token = (string)($_GET['token'] ?? '');
    $payment = subCheckoutPayment($publicId, $token);
    $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $method = subPaymentJson($payment)['methodLabel'];
    $amount = number_format((int)$payment['amount'], 0, ',', ' ') . ' FCFA';
    $pending = $payment['status'] === 'pending';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="' . mLang() . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . $e(t('Paiement de test', 'Test payment')) . '</title><style>'
        . 'body{font-family:-apple-system,system-ui,Roboto,sans-serif;margin:0;background:#f5f5f9;color:#0f1f1f}'
        . '.banner{background:#9a5b00;color:#fff;padding:12px 16px;font-weight:600;text-align:center}'
        . 'main{max-width:420px;margin:0 auto;padding:24px 16px}.card{background:#fff;border-radius:16px;padding:20px;margin-bottom:16px}'
        . '.amount{font-size:32px;font-weight:700;margin:8px 0}.row{display:flex;justify-content:space-between;padding:6px 0;color:#4b5c5c}'
        . 'button{width:100%;padding:16px;border:0;border-radius:12px;font-size:17px;font-weight:600;margin-bottom:12px}'
        . '.ok{background:#1a3a3a;color:#fff}.ko{background:#fce4e2;color:#b3261e}p{color:#4b5c5c}'
        . '@media(prefers-color-scheme:dark){body{background:#0b1414;color:#f1f6f5}.card{background:#152222}.row,p{color:#a9bdbb}.ok{background:#2dd4a8;color:#0e2222}}'
        . '</style></head><body><div class="banner">' . $e(t('MODE TEST — aucun débit réel', 'TEST MODE — nothing is charged')) . '</div><main>'
        . '<div class="card"><div>' . $e(t('Montant à payer', 'Amount to pay')) . '</div><div class="amount">' . $e($amount) . '</div>'
        . '<div class="row"><span>' . $e(t('Moyen de paiement', 'Payment method')) . '</span><span>' . $e($method) . '</span></div>'
        . '<div class="row"><span>' . $e(t('Contrat', 'Contract')) . '</span><span>' . $e((string)$payment['policy_number']) . '</span></div>'
        . '<div class="row"><span>' . $e(t('Référence', 'Reference')) . '</span><span>' . $e($payment['reference']) . '</span></div></div>';
    if ($pending) {
        echo '<form method="post"><input type="hidden" name="token" value="' . $e($token) . '">'
            . '<button class="ok" name="outcome" value="succeeded">' . $e(t('Simuler un paiement réussi', 'Simulate a successful payment')) . '</button>'
            . '<button class="ko" name="outcome" value="failed">' . $e(t('Simuler un échec', 'Simulate a failure')) . '</button></form>'
            . '<p>' . $e(t("Cette page remplace l'écran du prestataire de paiement (Wave, Orange Money, banque) tant que les comptes marchands ne sont pas ouverts.",
                'This page stands in for the payment provider (Wave, Orange Money, bank) until the merchant accounts are open.')) . '</p>';
    } else {
        echo '<p>' . $e(t('Ce paiement est terminé. Vous pouvez revenir à l\'application.', 'This payment is finished. You can go back to the app.')) . '</p>'
            . '<p><a href="' . $e($payment['return_url']) . '">' . $e(t("Revenir à l'application", 'Back to the app')) . '</a></p>';
    }
    echo '</main></body></html>';
    exit;
}

/** Outcome chosen on the test page. With a real provider this is the provider's signed webhook. */
function subCheckoutSubmit(string $publicId): never
{
    $payment = subCheckoutPayment($publicId, (string)($_POST['token'] ?? ''));
    $outcome = ($_POST['outcome'] ?? '') === 'succeeded' ? 'succeeded' : 'failed';
    if ($payment['status'] === 'pending') {
        $pdo = db();
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE mobile_payments SET status = ?, completed_at = NOW() WHERE id = ? AND status = 'pending'");
        $update->execute([$outcome, $payment['id']]);
        if ($update->rowCount() === 1 && $outcome === 'succeeded') {
            $pdo->prepare("UPDATE mobile_policies SET status = 'active', start_date = CURDATE(), end_date = DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 1 YEAR), INTERVAL 1 DAY)
                           WHERE id = ? AND status = 'pending_payment'")->execute([$payment['policy_id']]);
        }
        $pdo->commit();
    }
    header('Cache-Control: no-store');
    header('Location: ' . $payment['return_url'], true, 303);
    exit;
}
