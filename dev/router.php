<?php
// Dev router for `php -S`: serve real files from public/, send everything else to the front controller.
$file = __DIR__ . '/../legacy/ikarange/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/../legacy/ikarange/public/index.php';
