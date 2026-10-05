<?php

/**
 * Front controller – all requests come here.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/router.php';

route($_SERVER['REQUEST_URI'], requestMethod());
