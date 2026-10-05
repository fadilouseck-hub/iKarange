<?php

/**
 * POST /api/prestataire-logout
 * Logout prestataire user from tiers payant portal.
 */

unset($_SESSION['prestataire_user']);
session_regenerate_id(true);

jsonResponse(['ok' => true, 'redirect' => '/tiers-payant']);
