<?php
/**
 * Fix \u00XX unicode escapes in all PHP files under src/
 * Replace with actual UTF-8 characters.
 */

$basePath = dirname(__DIR__);
$fixed = 0;
$totalReplacements = 0;

function fixUnicodeEscapes(string $content): array {
    $count = 0;
    $result = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m) use (&$count) {
        $count++;
        return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
    }, $content);
    return [$result, $count];
}

// Fix all PHP files in src/
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;

    $path = $file->getPathname();
    $content = file_get_contents($path);

    if (strpos($content, '\\u00') === false && strpos($content, '\\u0') === false) continue;

    [$newContent, $count] = fixUnicodeEscapes($content);

    if ($count > 0) {
        file_put_contents($path, $newContent);
        $relPath = str_replace($basePath . '/', '', str_replace('\\', '/', $path));
        echo "Fixed: $relPath ($count replacements)\n";
        $fixed++;
        $totalReplacements += $count;
    }
}

// Also fix the database seed file
$seedFile = $basePath . '/database/002_seed.sql';
if (file_exists($seedFile)) {
    $content = file_get_contents($seedFile);
    [$newContent, $count] = fixUnicodeEscapes($content);
    if ($count > 0) {
        file_put_contents($seedFile, $newContent);
        echo "Fixed: database/002_seed.sql ($count replacements)\n";
        $fixed++;
        $totalReplacements += $count;
    }
}

echo "\n=== Summary ===\n";
echo "Files fixed: $fixed\n";
echo "Total replacements: $totalReplacements\n";

// Verify a sample
$bootstrap = file_get_contents($basePath . '/src/bootstrap.php');
if (preg_match("/label.*?Adh(.{10})/", $bootstrap, $m)) {
    echo "Sample check (bootstrap navItems): Adh" . $m[1] . "\n";
}
