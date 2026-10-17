<?php

/**
 * Public account-deletion request (Google Play "delete account" URL requirement). The member proves who they are
 * with the same username and password as in the app; the request goes into `mobile_deletion_requests`, the table
 * the app's Profil › Supprimer mon compte writes to, and is processed by the account manager in the same way.
 * Sign-in failures count towards the same rate limit as the mobile sign-in (`login_attempts`).
 */

require_once __DIR__ . '/legal.php';

$tenant = legalTenant($legalTenantId ?? 'assurplus');
$lang = legalLang();
$T = fn(string $fr, string $en): string => $lang === 'en' ? $en : $fr;
$app = e($tenant['appName']);
$path = '/' . $tenant['id'] . '/suppression-compte';
$delay = e($lang === 'en' ? $tenant['deletionDelayEn'] : $tenant['deletionDelay']);

$result = null; // 'done' | 'already' | error message
$identifier = '';
if (requestMethod() === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 1000);
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
    $cfg = $GLOBALS['appConfig']['rate_limit'];
    $attempts = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)');
    $attempts->execute([$ip, $cfg['login_window']]);

    if (!verifyCsrf()) {
        $result = $T('La page a expiré. Rechargez-la et réessayez.', 'The page expired. Reload it and try again.');
    } elseif ((int)$attempts->fetchColumn() >= $cfg['login_max']) {
        $result = $T('Trop de tentatives. Réessayez dans 15 minutes.', 'Too many attempts. Try again in 15 minutes.');
    } elseif ($identifier === '' || $password === '') {
        $result = $T('Identifiant et mot de passe requis.', 'Username and password are required.');
    } else {
        $stmt = db()->prepare("SELECT id, password_hash FROM adherents WHERE login = ? AND login IS NOT NULL AND statut <> 'inactif' LIMIT 1");
        $stmt->execute([$identifier]);
        $member = $stmt->fetch();
        if (!$member || !$member['password_hash'] || !password_verify($password, $member['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$ip]);
            $result = $T('Identifiant ou mot de passe incorrect.', 'Incorrect username or password.');
        } else {
            $pending = db()->prepare("SELECT COUNT(*) FROM mobile_deletion_requests WHERE adherent_id = ? AND status = 'requested'");
            $pending->execute([$member['id']]);
            if ((int)$pending->fetchColumn() > 0) {
                $result = 'already';
            } else {
                db()->prepare('INSERT INTO mobile_deletion_requests (adherent_id, reason) VALUES (?, ?)')
                    ->execute([$member['id'], trim('[web] ' . $reason)]);
                $result = 'done';
            }
            $identifier = '';
        }
    }
}

legalHeader($tenant, $T('Supprimer mon compte', 'Delete my account'), $lang, $path);
?>
  <h1><?= $T("Supprimer mon compte $app", "Delete my $app account") ?></h1>
  <p class="updated"><?= $T('Demande de suppression du compte et des données associées', 'Request deletion of your account and associated data') ?></p>

  <?php if ($result === 'done'): ?>
    <div class="ok"><?= $T(
        "Votre demande est enregistrée. Votre gestionnaire la traitera sous $delay et vous serez informé du résultat.",
        "Your request has been recorded. Your account manager will process it within $delay and let you know the outcome.") ?></div>
  <?php elseif ($result === 'already'): ?>
    <div class="ok"><?= $T(
        "Une demande de suppression est déjà en cours pour ce compte. Elle sera traitée sous $delay.",
        "A deletion request is already pending for this account. It will be processed within $delay.") ?></div>
  <?php else: ?>
    <div class="card">
      <h2 style="margin-top:0"><?= $T('Comment demander la suppression', 'How to request deletion') ?></h2>
      <ul>
        <li><?= $T("Dans l'application : Profil › Supprimer mon compte.", 'In the app: Profile › Delete my account.') ?></li>
        <li><?= $T('Ou avec le formulaire ci-dessous, avec votre identifiant et votre mot de passe habituels.', 'Or with the form below, using your usual username and password.') ?></li>
        <?php if (trim((string)$tenant['privacyEmail']) !== ''): ?>
          <li><?= $T('Ou par e-mail à', 'Or by email to') ?> <a href="mailto:<?= e($tenant['privacyEmail']) ?>"><?= e($tenant['privacyEmail']) ?></a>.</li>
        <?php endif; ?>
      </ul>

      <?php if (is_string($result)): ?><div class="error" role="alert"><?= e($result) ?></div><?php endif; ?>

      <form method="post" action="<?= e($path . ($lang === 'en' ? '?lang=en' : '')) ?>" autocomplete="on">
        <?= csrfField() ?>
        <label for="identifier"><?= $T('Identifiant', 'Username') ?></label>
        <input id="identifier" name="identifier" required autocomplete="username" value="<?= e($identifier) ?>">
        <label for="password"><?= $T('Mot de passe', 'Password') ?></label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <label for="reason"><?= $T('Motif (facultatif)', 'Reason (optional)') ?></label>
        <textarea id="reason" name="reason" rows="3" maxlength="1000"></textarea>
        <button type="submit"><?= $T('Demander la suppression de mon compte', 'Request deletion of my account') ?></button>
      </form>
    </div>
  <?php endif; ?>

  <h2><?= $T('Ce qui est supprimé', 'What is deleted') ?></h2>
  <ul>
    <li><?= $T("Votre accès à l'application (mot de passe), vos préférences, vos sessions et vos brouillons.", 'Your access to the app (password), your preferences, sessions and drafts.') ?></li>
    <li><?= $T('Les documents de votre dossier médical et vos messages (avis, assistant).', 'Your medical-records documents and your messages (feedback, assistant).') ?></li>
  </ul>
  <h2><?= $T('Ce qui est conservé', 'What is kept') ?></h2>
  <p><?= $T(
      'Les données du contrat, des sinistres et des paiements sont conservées pendant',
      'Contract, claim and payment records are kept for') ?> <?= legalValue($tenant, 'retentionPeriod', $lang) ?> <?= $T(
      "après la fin du contrat, comme l'exigent les obligations légales de l'assurance, puis supprimées. La suppression du compte n'annule pas votre contrat d'assurance : pour le résilier, contactez votre gestionnaire.",
      'after the contract ends, as required by the legal obligations of insurance, and then deleted. Deleting your account does not cancel your insurance contract: to terminate it, contact your account manager.') ?></p>
<?php
legalFooter($tenant, $lang);
