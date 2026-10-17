<?php

/**
 * Shared helpers for the public legal pages of the mobile apps (no login, no back-office layout).
 * Routes: see `src/router.php` (/assurplus/confidentialite, /assurplus/suppression-compte).
 */

function legalTenant(string $id): array
{
    $all = require basePath('config/public_legal.php');
    if (!isset($all[$id])) {
        http_response_code(404);
        exit('Not found');
    }
    return $all[$id] + ['id' => $id];
}

/** Published only when the operator has approved it and filled every required field. */
function legalIsPublished(array $tenant): bool
{
    foreach (['controller', 'address', 'privacyEmail', 'retentionPeriod'] as $field) {
        if (trim((string)($tenant[$field] ?? '')) === '') return false;
    }
    return !empty($tenant['published']);
}

function legalLang(): string
{
    return ($_GET['lang'] ?? '') === 'en' ? 'en' : 'fr';
}

/** Operator-provided value, or a visible placeholder in draft mode. */
function legalValue(array $tenant, string $field, string $lang): string
{
    $value = trim((string)($tenant[$field] ?? ''));
    if ($value !== '') return e($value);
    return '<mark>' . ($lang === 'en' ? '[to be completed]' : '[à compléter]') . '</mark>';
}

function legalHeader(array $tenant, string $title, string $lang, string $path): void
{
    $published = legalIsPublished($tenant);
    $other = $lang === 'en' ? $path : $path . '?lang=en';
    $brand = e($tenant['brandColor']);
    $dark = e($tenant['brandDark']);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    ?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (!$published): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <title><?= e($title) ?> – <?= e($tenant['appName']) ?></title>
  <style>
    :root { color-scheme: light dark; --brand: <?= $brand ?>; --dark: <?= $dark ?>; --bg: #F5F5F9; --surface: #fff; --text: #0B1F1A; --muted: #4A5A54; --border: #DDE3E6; }
    @media (prefers-color-scheme: dark) { :root { --bg: #0B1414; --surface: #152222; --text: #F1F6F5; --muted: #A9BDBB; --border: #2A3C3C; } }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif; }
    main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
    header.brand { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .mark { width: 40px; height: 40px; border-radius: 11px; background: var(--brand); position: relative; flex: none; }
    .mark::before, .mark::after { content: ""; position: absolute; background: #fff; border-radius: 2px; }
    .mark::before { left: 16px; top: 8px; width: 8px; height: 24px; }
    .mark::after { left: 8px; top: 16px; width: 24px; height: 8px; }
    .wordmark { font-weight: 800; font-size: 22px; letter-spacing: -0.03em; }
    .wordmark span { color: var(--brand); }
    nav.lang { margin-left: auto; font-size: 14px; }
    h1 { font-size: 28px; line-height: 1.2; margin: 24px 0 4px; }
    h2 { font-size: 19px; margin: 28px 0 8px; }
    .updated, .muted { color: var(--muted); font-size: 14px; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin: 16px 0; }
    .draft { background: #FFF1D6; color: #6B4000; border-radius: 12px; padding: 12px 16px; margin: 16px 0; font-size: 14px; }
    .ok { background: #DDF5EA; color: #0E5C41; border-radius: 12px; padding: 12px 16px; }
    .error { background: #FCE4E2; color: #8C1D18; border-radius: 12px; padding: 12px 16px; }
    a { color: #007A5E; }
    @media (prefers-color-scheme: dark) { a { color: var(--brand); } }
    ul { padding-left: 20px; }
    label { display: block; font-weight: 600; font-size: 14px; margin: 14px 0 6px; }
    input, textarea { width: 100%; font: inherit; color: var(--text); background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px; }
    button { margin-top: 18px; width: 100%; font: inherit; font-weight: 600; color: #fff; background: #B3261E; border: 0; border-radius: 12px; padding: 14px; cursor: pointer; }
    mark { background: #FFE08A; color: #000; padding: 0 3px; border-radius: 3px; }
  </style>
</head>
<body>
<main>
  <header class="brand">
    <div class="mark" aria-hidden="true"></div>
    <div class="wordmark">assur<span>+</span></div>
    <nav class="lang"><a href="<?= e($other) ?>"><?= $lang === 'en' ? 'Français' : 'English' ?></a></nav>
  </header>
  <?php if (!$published): ?>
    <div class="draft"><?= $lang === 'en'
        ? 'Draft pending legal validation by the operator — not published.'
        : 'Projet en attente de validation juridique par l\'opérateur — non publié.' ?></div>
  <?php endif;
}

function legalFooter(array $tenant, string $lang): void
{
    $id = e($tenant['id']);
    ?>
  <p class="muted" style="margin-top:32px">
    <a href="/<?= $id ?>/confidentialite<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $lang === 'en' ? 'Privacy policy' : 'Politique de confidentialité' ?></a> ·
    <a href="/<?= $id ?>/suppression-compte<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $lang === 'en' ? 'Delete my account' : 'Supprimer mon compte' ?></a>
  </p>
</main>
</body>
</html>
    <?php
}
