<?php
$file = __DIR__ . '/002_seed.sql';
$content = file_get_contents($file);

// Replace \uXXXX patterns with actual UTF-8 characters
$content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m) {
    return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
}, $content);

file_put_contents($file, $content);

// Verify
$remaining = preg_match_all('/\\\\u[0-9a-fA-F]{4}/', $content);
echo "Fixed! Remaining unicode escapes: $remaining\n";

// Show a sample line
$lines = explode("\n", $content);
foreach ($lines as $line) {
    if (strpos($line, 'SNIM') !== false) {
        echo "Sample: " . substr($line, 0, 120) . "\n";
        break;
    }
}
