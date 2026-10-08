<?php

/**
 * Router – maps URI patterns to handler files.
 */

function route(string $uri, string $method): void
{
    $path = parse_url($uri, PHP_URL_PATH);
    $path = '/' . trim($path, '/');

    // Static assets
    if (preg_match('/\.(css|js|png|jpg|jpeg|webp|svg|ico|json|woff2?)$/i', $path)) {
        return;
    }

    // Mobile API (Assur Plus apps): token-authenticated, self-contained router.
    if (str_starts_with($path, '/api/mobile/v1/')) {
        require basePath('src/api/mobile/index.php');
        mobileRoute($path, $method);
    }

    $routes = [
        // ── Pages ──
        'GET /'                              => 'pages/dashboard.php',
        'GET /login'                         => 'pages/login.php',
        'GET /tiers-payant'                  => 'pages/tiers-payant.php',
        'GET /rapports'                      => 'pages/rapports.php',
        'GET /remboursements'                => 'pages/remboursements.php',
        'GET /portail-adherent'              => 'pages/portail-adherent.php',
        'GET /assureurs'                     => 'pages/assureurs.php',
        'GET /entreprises'                   => 'pages/entreprises.php',
        'GET /adherents'                     => 'pages/adherents.php',
        'GET /prestataires'                  => 'pages/prestataires.php',
        'GET /utilisateurs-prestataires'     => 'pages/utilisateurs-prestataires.php',
        'GET /prises-en-charge'              => 'pages/prises-en-charge.php',
        'GET /prises-en-charge/{id}'         => 'pages/pec-detail.php',
        'GET /factures-prestataires'         => 'pages/factures-prestataires.php',
        'GET /factures-entreprises'          => 'pages/factures-entreprises.php',
        'GET /primes-budget'                 => 'pages/primes-budget.php',
        'GET /statistiques'                  => 'pages/statistiques.php',
        'GET /compagnies-assurance'          => 'pages/compagnies-assurance.php',
        'GET /gestion-acces'                 => 'pages/gestion-acces.php',

        // ── API – Auth ──
        'POST /api/login'                    => 'api/auth/login.php',
        'POST /api/logout'                   => 'api/auth/logout.php',
        'POST /api/prestataire-login'        => 'api/auth/prestataire-login.php',
        'POST /api/prestataire-logout'       => 'api/auth/prestataire-logout.php',

        // ── API – Dashboard ──
        'GET /api/dashboard'                 => 'api/dashboard.php',

        // ── API – Entreprises ──
        'GET /api/entreprises'               => 'api/entreprises/list.php',
        'POST /api/entreprises'              => 'api/entreprises/create.php',
        'GET /api/entreprises/{id}'          => 'api/entreprises/show.php',
        'POST /api/entreprises/{id}'         => 'api/entreprises/update.php',
        'DELETE /api/entreprises/{id}'       => 'api/entreprises/delete.php',

        // ── API – Adherents ──
        'GET /api/adherents'                 => 'api/adherents/list.php',
        'POST /api/adherents'                => 'api/adherents/create.php',
        'GET /api/adherents/{id}'            => 'api/adherents/show.php',
        'POST /api/adherents/{id}'           => 'api/adherents/update.php',
        'DELETE /api/adherents/{id}'         => 'api/adherents/delete.php',
        'POST /api/adherents/import'         => 'api/adherents/import.php',
        'GET /api/adherents/template'        => 'api/adherents/template.php',
        'GET /api/adherents/{id}/wallet-pass' => 'api/adherents/wallet-pass.php',
        'GET /api/adherents/{id}/photo'       => 'api/adherents/photo.php',

        // ── API – Prestataires ──
        'GET /api/prestataires'              => 'api/prestataires/list.php',
        'POST /api/prestataires'             => 'api/prestataires/create.php',
        'GET /api/prestataires/{id}'         => 'api/prestataires/show.php',
        'POST /api/prestataires/{id}'        => 'api/prestataires/update.php',
        'DELETE /api/prestataires/{id}'      => 'api/prestataires/delete.php',

        // ── API – Utilisateurs Prestataires ──
        'GET /api/utilisateurs-prestataires'         => 'api/utilisateurs-prestataires/list.php',
        'POST /api/utilisateurs-prestataires'        => 'api/utilisateurs-prestataires/create.php',
        'POST /api/utilisateurs-prestataires/{id}'   => 'api/utilisateurs-prestataires/update.php',
        'DELETE /api/utilisateurs-prestataires/{id}' => 'api/utilisateurs-prestataires/delete.php',

        // ── API – Prises en charge ──
        'GET /api/pec'                       => 'api/pec/list.php',
        'POST /api/pec'                      => 'api/pec/create.php',
        'GET /api/pec/{id}'                  => 'api/pec/show.php',
        'POST /api/pec/{id}'                 => 'api/pec/update.php',
        'DELETE /api/pec/{id}'               => 'api/pec/delete.php',

        // ── API – Factures Prestataires ──
        'GET /api/factures-prestataires'             => 'api/factures-prestataires/list.php',
        'POST /api/factures-prestataires'            => 'api/factures-prestataires/create.php',
        'POST /api/factures-prestataires/{id}'       => 'api/factures-prestataires/update.php',
        'DELETE /api/factures-prestataires/{id}'     => 'api/factures-prestataires/delete.php',
        'POST /api/factures-prestataires/generate'   => 'api/factures-prestataires/generate.php',
        'GET /api/factures-prestataires/{id}/etat'   => 'api/factures-prestataires/etat.php',
        'GET /api/factures-prestataires/etat-global' => 'api/factures-prestataires/etat-global.php',

        // ── API – Factures Entreprises ──
        'GET /api/factures-entreprises'              => 'api/factures-entreprises/list.php',
        'POST /api/factures-entreprises'             => 'api/factures-entreprises/create.php',
        'POST /api/factures-entreprises/{id}'        => 'api/factures-entreprises/update.php',
        'DELETE /api/factures-entreprises/{id}'      => 'api/factures-entreprises/delete.php',
        'POST /api/factures-entreprises/generate'    => 'api/factures-entreprises/generate.php',
        'GET /api/factures-entreprises/{id}/etat'    => 'api/factures-entreprises/etat.php',
        'GET /api/factures-entreprises/etat-global'  => 'api/factures-entreprises/etat-global.php',

        // ── API – Baremes ──
        'GET /api/baremes'                   => 'api/baremes/list.php',
        'POST /api/baremes'                  => 'api/baremes/save.php',
        'GET /api/baremes/lookup'            => 'api/baremes/lookup.php',

        // ── API – Primes & Budget ──
        'GET /api/primes'                    => 'api/primes/list.php',
        'POST /api/primes'                   => 'api/primes/create.php',
        'POST /api/primes/{id}'              => 'api/primes/update.php',
        'DELETE /api/primes/{id}'            => 'api/primes/delete.php',

        // ── API – Compagnies Assurance ──
        'GET /api/compagnies'                => 'api/compagnies/list.php',
        'POST /api/compagnies'               => 'api/compagnies/create.php',
        'POST /api/compagnies/{id}'          => 'api/compagnies/update.php',
        'DELETE /api/compagnies/{id}'        => 'api/compagnies/delete.php',

        // ── API – Gestion Acces ──
        'GET /api/acces/users'               => 'api/acces/users.php',
        'POST /api/acces/users'              => 'api/acces/users.php',
        'POST /api/acces/users/{id}'         => 'api/acces/users.php',
        'DELETE /api/acces/users/{id}'       => 'api/acces/users.php',
        'GET /api/acces/profiles'            => 'api/acces/profiles.php',
        'POST /api/acces/profiles'           => 'api/acces/profiles.php',
        'POST /api/acces/profiles/{id}'      => 'api/acces/profiles.php',
        'DELETE /api/acces/profiles/{id}'    => 'api/acces/profiles.php',

        // ── API – Tiers Payant (prestataire portal) ──
        'GET /api/tiers-payant/search'          => 'api/tiers-payant/search.php',
        'GET /api/tiers-payant/couverture/{id}' => 'api/tiers-payant/couverture.php',
        'POST /api/tiers-payant/pec'            => 'api/tiers-payant/pec.php',
        'GET /api/tiers-payant/pec-receipt/{id}' => 'api/tiers-payant/pec-receipt.php',
        'GET /api/tiers-payant/facturation'     => 'api/tiers-payant/facturation.php',
        'POST /api/tiers-payant/facture-submit' => 'api/tiers-payant/facture-submit.php',
        'GET /api/tiers-payant/facture-doc/{id}' => 'api/tiers-payant/facture-document.php',

        // ── API – Statistiques ──
        'GET /api/statistiques'              => 'api/statistiques.php',

        // ── API – Rapports ──
        'GET /api/rapports/beneficiaires'    => 'api/rapports/beneficiaires.php',
        'GET /api/rapports/prestations'      => 'api/rapports/prestations.php',
        'GET /api/rapports/remboursements'   => 'api/rapports/remboursements.php',
        'GET /api/rapports/prestataires'     => 'api/rapports/prestataires.php',

        // ── API – Remboursements (admin) ──
        'GET /api/remboursements'                        => 'api/remboursements/list.php',
        'POST /api/remboursements/{id}'                  => 'api/remboursements/update.php',

        // ── API – Adherent portal ──
        'POST /api/adherent-login'                   => 'api/auth/adherent-login.php',
        'POST /api/adherent-logout'                  => 'api/auth/adherent-logout.php',
        'GET /api/portail-adherent/dashboard'        => 'api/portail-adherent/dashboard.php',
        'GET /api/portail-adherent/pec'              => 'api/portail-adherent/pec.php',
        'GET /api/portail-adherent/baremes'          => 'api/portail-adherent/baremes.php',
        'POST /api/portail-adherent/password'        => 'api/portail-adherent/password.php',
        'POST /api/portail-adherent/profile'         => 'api/portail-adherent/profile.php',
        'GET /api/portail-adherent/remboursements'   => 'api/portail-adherent/remboursements.php',
        'POST /api/portail-adherent/remboursement-create' => 'api/portail-adherent/remboursement-create.php',
        'GET /api/portail-adherent/remboursement-doc/{id}' => 'api/portail-adherent/remboursement-documents.php',

        // ── API – Assureurs ──
        'GET /api/assureurs'                 => 'api/assureurs/list.php',
        'POST /api/assureurs'                => 'api/assureurs/create.php',
        'POST /api/assureurs/{id}'           => 'api/assureurs/update.php',
        'POST /api/switch-org'               => 'api/assureurs/switch-org.php',

        // ── API – Assistant (smart search) ──
        'POST /api/assistant'                => 'api/assistant.php',
    ];

    $routeKey = "$method $path";

    // Direct match
    if (isset($routes[$routeKey])) {
        require basePath('src/' . $routes[$routeKey]);
        return;
    }

    // Pattern match with {id}
    foreach ($routes as $pattern => $file) {
        if (!str_contains($pattern, '{')) continue;

        [$routeMethod, $routePath] = explode(' ', $pattern, 2);
        if ($routeMethod !== $method) continue;

        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[0-9]+)', $routePath);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            $_GET = array_merge($_GET, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            require basePath('src/' . $file);
            return;
        }
    }

    // Install route
    if ($path === '/install' && $method === 'GET') {
        $installFile = basePath('install.php');
        if (file_exists($installFile)) {
            require $installFile;
            return;
        }
    }

    // 404
    http_response_code(404);
    if (isApiRequest()) {
        jsonResponse(['error' => 'Non trouvé'], 404);
    }
    require basePath('src/pages/404.php');
}
