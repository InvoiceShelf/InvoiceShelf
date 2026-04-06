<?php

/**
 * Generate manifest.json for the InvoiceShelf updater.
 *
 * Usage: php scripts/generate-manifest.php [base-directory]
 *
 * Outputs a sorted JSON array of all relative file paths in the given
 * directory. The manifest is written to {base-directory}/manifest.json
 * and is used by the updater to detect and remove stale files after
 * copying a new release.
 */
$basePath = rtrim($argv[1] ?? '.', '/');

if (! is_dir($basePath)) {
    fwrite(STDERR, "Error: '{$basePath}' is not a directory.\n");
    exit(1);
}

$excludedPrefixes = [
    '.env',
    '.git/',
    'storage/',
    'vendor/',
    'node_modules/',
    'Modules/',
    'bootstrap/cache/',
    'public/storage/',
];

$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $relativePath = substr($file->getPathname(), strlen($basePath) + 1);

    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($relativePath, $prefix)) {
            continue 2;
        }
    }

    $files[] = $relativePath;
}

sort($files);

$json = json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($basePath.'/manifest.json', $json."\n");

$count = count($files);
fwrite(STDOUT, "manifest.json written with {$count} files.\n");
