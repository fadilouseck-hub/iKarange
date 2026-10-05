<?php
/**
 * Main layout shell – I'KARANGE custom dark teal sidebar theme.
 *
 * Variables expected:
 *   $pageTitle  – string
 *   $activeTab  – string
 *   $showNav    – bool (default true)
 */
$pageTitle  = $pageTitle ?? "I'KARANGE";
$activeTab  = $activeTab ?? 'dashboard';
$showNav    = $showNav ?? (bool)authUser();
$user       = authUser();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#2dd4a8">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= e($pageTitle) ?> – I'KARANGE</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/icons/icon-192.png">
  <link rel="apple-touch-icon" href="/icons/icon-192.png">

  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

  <!-- Alpine.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>

  <style>
    [x-cloak] { display: none !important; }

    :root {
      --sidebar-bg: #1a3a3a;
      --sidebar-hover: #264a4a;
      --sidebar-active: #2d5454;
      --sidebar-text: #b0c4c4;
      --sidebar-text-active: #ffffff;
      --sidebar-width: 260px;
      --primary: #2dd4a8;
      --primary-dark: #25b890;
      --bg-body: #f5f5f9;
    }

    * { box-sizing: border-box; }

    body {
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg-body);
      margin: 0;
      min-height: 100vh;
    }

    /* ── Sidebar ── */
    .ik-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      z-index: 1040;
      transition: transform .3s ease;
      overflow-y: auto;
    }

    .ik-sidebar-brand {
      padding: 1.25rem 1.25rem 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
    }

    .ik-sidebar-brand .brand-icon {
      width: 38px;
      height: 38px;
      flex-shrink: 0;
    }

    .ik-sidebar-brand .brand-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .ik-sidebar-brand .brand-text {
      color: #fff;
      font-weight: 700;
      font-size: 1.1rem;
      line-height: 1.2;
    }

    .ik-sidebar-brand .brand-sub {
      color: var(--sidebar-text);
      font-size: 0.7rem;
      font-weight: 400;
    }

    .ik-nav {
      list-style: none;
      padding: 0.5rem 0;
      margin: 0;
      flex: 1;
    }

    .ik-nav li a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.6rem 1.25rem;
      color: var(--sidebar-text);
      text-decoration: none;
      font-size: 0.875rem;
      transition: all .15s ease;
      border-left: 3px solid transparent;
    }

    .ik-nav li a:hover {
      background: var(--sidebar-hover);
      color: #fff;
    }

    .ik-nav li.active a {
      background: var(--sidebar-active);
      color: var(--sidebar-text-active);
      border-left-color: var(--primary);
      font-weight: 500;
    }

    .ik-nav li a i {
      font-size: 1.25rem;
      width: 24px;
      text-align: center;
      flex-shrink: 0;
    }

    .ik-sidebar-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid rgba(255,255,255,0.08);
    }

    .ik-sidebar-footer .user-name {
      color: #fff;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .ik-sidebar-footer .user-role {
      color: var(--sidebar-text);
      font-size: 0.75rem;
    }

    .ik-sidebar-footer .logout-link {
      color: var(--primary);
      font-size: 0.8rem;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.3rem;
      margin-top: 0.5rem;
    }

    .ik-sidebar-footer .logout-link:hover {
      text-decoration: underline;
    }

    /* ── Main content ── */
    .ik-main {
      margin-left: var(--sidebar-width);
      min-height: 100vh;
    }

    .ik-topbar {
      background: #fff;
      padding: 0.75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #e9ecef;
    }

    .ik-topbar h1 {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 0;
      color: #333;
    }

    .ik-topbar .user-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #666;
      font-size: 0.85rem;
    }

    .ik-topbar .user-badge .avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.8rem;
    }

    .ik-content {
      padding: 1.5rem;
    }

    /* ── Mobile hamburger ── */
    .ik-hamburger {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #333;
      cursor: pointer;
      padding: 0;
    }

    .ik-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1035;
    }

    @media (max-width: 991.98px) {
      .ik-sidebar {
        transform: translateX(-100%);
      }
      .ik-sidebar.open {
        transform: translateX(0);
      }
      .ik-overlay.open {
        display: block;
      }
      .ik-main {
        margin-left: 0;
      }
      .ik-hamburger {
        display: inline-flex;
      }
    }

    /* ── Cards ── */
    .ik-card {
      background: #fff;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      padding: 1.25rem;
    }

    /* ── Stat cards ── */
    .stat-card {
      background: #fff;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      padding: 1.25rem;
      position: relative;
    }

    .stat-card .stat-label {
      font-size: 0.8rem;
      color: #888;
      margin-bottom: 0.25rem;
    }

    .stat-card .stat-value {
      font-size: 1.6rem;
      font-weight: 700;
      color: #333;
    }

    .stat-card .stat-sub {
      font-size: 0.75rem;
      color: #999;
    }

    .stat-card .stat-icon {
      position: absolute;
      top: 1.25rem;
      right: 1.25rem;
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }

    /* ── Tables ── */
    .ik-table {
      width: 100%;
      border-collapse: collapse;
    }

    .ik-table thead th {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: #888;
      font-weight: 600;
      padding: 0.75rem 1rem;
      border-bottom: 2px solid #e9ecef;
      white-space: nowrap;
    }

    .ik-table tbody tr {
      border-bottom: 1px solid #f0f0f0;
      transition: background .1s;
    }

    .ik-table tbody tr:hover {
      background: #f8f9fa;
    }

    .ik-table tbody td {
      padding: 0.75rem 1rem;
      font-size: 0.875rem;
      color: #333;
      vertical-align: middle;
    }

    .ik-table .row-name {
      font-weight: 600;
      color: #1a3a3a;
    }

    .ik-table .row-sub {
      font-size: 0.75rem;
      color: #999;
    }

    /* ── Status badges ── */
    .badge-status {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .badge-actif, .badge-agree, .badge-approuvee, .badge-active, .badge-payee, .badge-validee {
      background: #e6f9f1;
      color: #15803d;
    }

    .badge-en_attente, .badge-a_facturer {
      background: #fff3e0;
      color: #e65100;
    }

    .badge-reglee {
      background: #e3f2fd;
      color: #1565c0;
    }

    .badge-rejetee, .badge-resilie, .badge-inactif, .badge-suspendu {
      background: #fce4ec;
      color: #c62828;
    }

    .badge-facturee {
      background: #f3e5f5;
      color: #7b1fa2;
    }

    .badge-partiel {
      background: #fff8e1;
      color: #f57f17;
    }

    /* ── Type badges (prestataires) ── */
    .badge-type {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 600;
    }

    .badge-clinique   { background: #e3f2fd; color: #1565c0; }
    .badge-hopital    { background: #fce4ec; color: #c62828; }
    .badge-pharmacie  { background: #e8f5e9; color: #2e7d32; }
    .badge-laboratoire { background: #fff3e0; color: #e65100; }
    .badge-centre_imagerie { background: #f3e5f5; color: #7b1fa2; }
    .badge-dentiste   { background: #fce4ec; color: #ad1457; }

    /* ── Buttons ── */
    .btn-primary {
      background: var(--primary) !important;
      border-color: var(--primary) !important;
      color: #fff !important;
    }

    .btn-primary:hover {
      background: var(--primary-dark) !important;
      border-color: var(--primary-dark) !important;
    }

    .btn-outline-primary {
      color: var(--primary) !important;
      border-color: var(--primary) !important;
    }

    .btn-outline-primary:hover {
      background: var(--primary) !important;
      color: #fff !important;
    }

    /* ── Modals ── */
    .ik-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1050;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .ik-modal {
      background: #fff;
      border-radius: 12px;
      width: 100%;
      max-width: 560px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }

    .ik-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 1.5rem 0.75rem;
    }

    .ik-modal-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 0;
    }

    .ik-modal-header .close-btn {
      background: none;
      border: none;
      font-size: 1.25rem;
      color: #666;
      cursor: pointer;
      padding: 0.25rem;
      line-height: 1;
    }

    .ik-modal-body {
      padding: 0.75rem 1.5rem 1.25rem;
    }

    .ik-modal-footer {
      padding: 0.75rem 1.5rem 1.25rem;
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }

    /* ── Form styling ── */
    .form-label {
      font-size: 0.82rem;
      font-weight: 500;
      color: #555;
      margin-bottom: 0.25rem;
    }

    .form-control, .form-select {
      font-size: 0.875rem;
      border-radius: 6px;
      border: 1px solid #d5d9dd;
      padding: 0.5rem 0.75rem;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.2rem rgba(45,212,168,0.15);
    }

    /* ── Action icons ── */
    .action-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0.25rem;
      font-size: 1.1rem;
      color: #888;
      transition: color .15s;
    }

    .action-btn:hover { color: #333; }
    .action-btn.edit:hover { color: var(--primary); }
    .action-btn.delete:hover { color: #dc3545; }
    .action-btn.view:hover { color: #1565c0; }
    .action-btn.download:hover { color: #7b1fa2; }

    /* ── Search bar ── */
    .ik-search {
      position: relative;
      max-width: 380px;
    }

    .ik-search i {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
      font-size: 1.1rem;
    }

    .ik-search input {
      padding-left: 2.5rem;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
      background: #fff;
      height: 40px;
      font-size: 0.875rem;
      width: 100%;
    }

    .ik-search input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.15rem rgba(45,212,168,0.12);
      outline: none;
    }

    /* ── Toggle switch ── */
    .form-check-input:checked {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    /* ── Skeleton loading ── */
    .skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s infinite;
      border-radius: 4px;
    }

    @keyframes shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }

    /* ── Empty state ── */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #999;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #ddd;
    }

    /* ── Assistant widget ── */
    .ik-assistant {
      position: relative;
      max-width: 420px;
      flex: 1;
      margin: 0 1rem;
    }

    .ik-assistant-input {
      display: flex;
      align-items: center;
      background: var(--bg-body);
      border: 1px solid #e0e0e0;
      border-radius: 24px;
      padding: 0 0.75rem;
      height: 38px;
      transition: border-color .2s, box-shadow .2s;
    }

    .ik-assistant-input:focus-within {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(45,212,168,0.12);
    }

    .ik-assistant-input i {
      font-size: 1.1rem;
      color: var(--primary);
      flex-shrink: 0;
    }

    .ik-assistant-input input {
      border: none;
      background: transparent;
      outline: none;
      flex: 1;
      font-size: 0.82rem;
      padding: 0 0.5rem;
      color: #333;
    }

    .ik-assistant-input input::placeholder {
      color: #aaa;
    }

    .ik-assistant-panel {
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      right: 0;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
      border: 1px solid #e9ecef;
      z-index: 1060;
      max-height: 420px;
      overflow-y: auto;
      animation: assistSlideDown .2s ease;
    }

    @keyframes assistSlideDown {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .ik-assistant-panel .assist-msg {
      padding: 1rem 1.25rem;
      font-size: 0.85rem;
      color: #333;
      line-height: 1.5;
    }

    .ik-assistant-panel .assist-value {
      text-align: center;
      padding: 0.75rem 1.25rem;
    }

    .ik-assistant-panel .assist-value .big-num {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary-dark);
    }

    .ik-assistant-panel .assist-value .big-label {
      font-size: 0.75rem;
      color: #888;
      margin-top: 0.15rem;
    }

    .ik-assistant-panel .assist-link {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1.25rem;
      color: var(--primary-dark);
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 500;
      border-top: 1px solid #f0f0f0;
      transition: background .15s;
    }

    .ik-assistant-panel .assist-link:hover {
      background: #f8f9fa;
    }

    .ik-assistant-panel .assist-link i {
      font-size: 1.1rem;
    }

    .ik-assistant-panel .assist-items {
      border-top: 1px solid #f0f0f0;
    }

    .ik-assistant-panel .assist-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.6rem 1.25rem;
      text-decoration: none;
      color: #333;
      font-size: 0.82rem;
      transition: background .15s;
      border-bottom: 1px solid #f8f8f8;
    }

    .ik-assistant-panel .assist-item:hover {
      background: #f8f9fa;
    }

    .ik-assistant-panel .assist-item .item-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: var(--bg-body);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: var(--primary-dark);
      flex-shrink: 0;
    }

    .ik-assistant-panel .assist-item .item-label {
      font-weight: 500;
    }

    .ik-assistant-panel .assist-item .item-sub {
      font-size: 0.72rem;
      color: #888;
    }

    .ik-assistant-panel .assist-item .item-entity {
      margin-left: auto;
      font-size: 0.65rem;
      color: #aaa;
      background: #f0f0f0;
      padding: 0.15rem 0.4rem;
      border-radius: 4px;
      flex-shrink: 0;
    }

    .ik-assistant-panel .assist-chips {
      padding: 0.6rem 1.25rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      border-top: 1px solid #f0f0f0;
    }

    .ik-assistant-panel .assist-chip {
      display: inline-block;
      padding: 0.25rem 0.65rem;
      border-radius: 16px;
      background: var(--bg-body);
      color: #555;
      font-size: 0.72rem;
      cursor: pointer;
      border: 1px solid #e0e0e0;
      transition: all .15s;
    }

    .ik-assistant-panel .assist-chip:hover {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .ik-assistant-loading {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      padding: 1rem 1.25rem;
      justify-content: center;
    }

    .ik-assistant-loading span {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--primary);
      animation: assistDot 1.2s infinite;
    }

    .ik-assistant-loading span:nth-child(2) { animation-delay: 0.2s; }
    .ik-assistant-loading span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes assistDot {
      0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
      40% { opacity: 1; transform: scale(1.1); }
    }

    @media (max-width: 767.98px) {
      .ik-assistant {
        max-width: 100%;
        margin: 0 0.5rem;
      }
    }
  </style>

  <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>

<body>

  <!-- Toast area -->
  <div id="toast-area" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:1090"></div>

  <?php if ($showNav): ?>

  <!-- Overlay (mobile) -->
  <div class="ik-overlay" id="sidebarOverlay" onclick="document.getElementById('sidebar').classList.remove('open');this.classList.remove('open');"></div>

  <!-- Sidebar -->
  <aside class="ik-sidebar" id="sidebar">
    <a href="/" class="ik-sidebar-brand">
      <span class="brand-icon"><img src="/images/logo-icon.png" alt="I'KARANGÉ"></span>
      <span>
        <span class="brand-text">I'KARANG&Eacute;</span><br>
        <span class="brand-sub">Gestion Assurance &amp; Mutuelle</span>
      </span>
    </a>

    <ul class="ik-nav">
      <?php foreach (navItems() as $item):
        if (!hasPermission($item['key']) && $item['key'] !== 'dashboard') continue;
      ?>
      <li class="<?= $activeTab === $item['key'] ? 'active' : '' ?>">
        <a href="<?= $item['href'] ?>">
          <i class="<?= $item['icon'] ?>"></i>
          <span><?= $item['label'] ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($user): ?>
    <div class="ik-sidebar-footer">
      <div class="user-name"><?= e($user['full_name'] ?? $user['login']) ?></div>
      <div class="user-role"><?= e(ucfirst($user['role'] ?? 'gestionnaire')) ?></div>
      <a href="javascript:void(0);" class="logout-link"
         onclick="fetch('/api/logout',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF}}).then(()=>location.href='/login')">
        <i class="bx bx-log-out"></i> Se d&eacute;connecter
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- Main content area -->
  <div class="ik-main">
    <div class="ik-topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="ik-hamburger" onclick="document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');">
          <i class="bx bx-menu"></i>
        </button>
        <h1 class="d-none d-md-block"><?= e($pageTitle) ?></h1>
      </div>

      <?php if ($user): ?>
      <?php if (isSuperAdmin()): ?>
      <!-- Org switcher for super-admin -->
      <div style="position:relative;" x-data="{ orgOpen: false }">
        <button @click="orgOpen = !orgOpen" @click.outside="orgOpen = false"
                style="display:flex;align-items:center;gap:0.4rem;background:var(--bg-body);border:1px solid #e0e0e0;border-radius:20px;padding:0.3rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#333;white-space:nowrap;">
          <i class="bx bx-globe" style="color:var(--primary);font-size:0.9rem;"></i>
          <span><?= e(currentOrgName()) ?></span>
          <i class="bx bx-chevron-down" style="font-size:0.8rem;color:#888;"></i>
        </button>
        <div x-show="orgOpen" x-cloak style="position:absolute;top:calc(100% + 4px);left:0;min-width:220px;background:#fff;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.12);border:1px solid #e9ecef;z-index:1060;overflow:hidden;">
          <?php foreach (allOrgs() as $org): ?>
          <a href="javascript:void(0);" style="display:block;padding:0.5rem 1rem;font-size:0.82rem;color:#333;text-decoration:none;border-bottom:1px solid #f0f0f0;<?= (int)$org['id'] === orgId() ? 'background:rgba(45,212,168,0.08);font-weight:600;color:var(--primary-dark);' : '' ?>"
             onclick="fetch('/api/switch-org',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({org_id:<?= (int)$org['id'] ?>})}).then(()=>location.reload())">
            <?= e($org['name']) ?>
            <?php if ((int)$org['id'] === orgId()): ?><i class="bx bx-check" style="float:right;color:var(--primary);"></i><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <!-- Assistant search widget -->
      <div class="ik-assistant" x-data="assistantWidget()" @click.outside="open = false" @keydown.escape.window="open = false">
        <div class="ik-assistant-input">
          <i class="bx bx-bot"></i>
          <input type="text"
                 placeholder="Posez une question... (ex: combien d'adherents ?)"
                 x-model="query"
                 @focus="onFocus()"
                 @keydown.enter.prevent="ask()"
                 @input.debounce.400ms="autoAsk()">
          <template x-if="query.length > 0">
            <i class="bx bx-x" style="cursor:pointer;color:#aaa;" @click="clear()"></i>
          </template>
        </div>

        <!-- Results panel -->
        <div class="ik-assistant-panel" x-show="open" x-cloak>
          <!-- Loading -->
          <template x-if="loading">
            <div class="ik-assistant-loading">
              <span></span><span></span><span></span>
            </div>
          </template>

          <!-- Result -->
          <template x-if="!loading && result">
            <div>
              <!-- Message -->
              <div class="assist-msg" x-text="result.message"></div>

              <!-- Big value (count/aggregation) -->
              <template x-if="result.value !== undefined && result.value !== null">
                <div class="assist-value">
                  <div class="big-num" x-text="result.formatted || result.value"></div>
                  <div class="big-label" x-text="result.label || ''"></div>
                </div>
              </template>

              <!-- Navigation link -->
              <template x-if="result.link">
                <a :href="result.link" class="assist-link" @click="open = false">
                  <i :class="result.icon || 'bx bx-right-arrow-alt'"></i>
                  <span x-text="result.linkLabel || 'Ouvrir'"></span>
                  <i class="bx bx-chevron-right" style="margin-left:auto;"></i>
                </a>
              </template>

              <!-- Search result items -->
              <template x-if="result.items && result.items.length > 0">
                <div class="assist-items">
                  <template x-for="item in result.items" :key="item.label + item.sub">
                    <a :href="item.link" class="assist-item" @click="open = false">
                      <div class="item-icon"><i :class="item.icon"></i></div>
                      <div>
                        <div class="item-label" x-text="item.label"></div>
                        <div class="item-sub" x-text="item.sub"></div>
                      </div>
                      <span class="item-entity" x-text="item.entity"></span>
                    </a>
                  </template>
                </div>
              </template>

              <!-- Suggestion chips -->
              <template x-if="result.suggestions && result.suggestions.length > 0">
                <div class="assist-chips">
                  <template x-for="s in result.suggestions" :key="s">
                    <span class="assist-chip" @click="useSuggestion(s)" x-text="s"></span>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>

      <div class="user-badge">
        <span class="avatar"><?= strtoupper(mb_substr($user['full_name'] ?? $user['login'], 0, 1)) ?></span>
        <span class="d-none d-sm-inline"><?= e($user['full_name'] ?? $user['login']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="ik-content">
  <?php /* ── Page content starts here ── */ ?>
  <?php else: ?>
  <!-- Auth layout (no sidebar) -->
  <?php endif; ?>
