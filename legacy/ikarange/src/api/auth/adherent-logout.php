<?php

/**
 * POST /api/adherent-logout
 */

unset($_SESSION['adherent_user']);
session_regenerate_id(true);
jsonResponse(['ok' => true]);
