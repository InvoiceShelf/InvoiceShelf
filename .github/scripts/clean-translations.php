<?php

/**
 * Clean the Crowdin translations in lang/ against lang/en.json.
 *
 * Usage: php .github/scripts/clean-translations.php [--check] [lang-dir]
 *
 * For every locale file but en.json it drops what would show wrong in the
 * app, so English shows instead (both the SPA and SpaTranslations fall back
 * to it):
 *
 * - keys en.json no longer has, left behind after a string was removed;
 * - a translation whose {placeholders} differ from the English ones, such as
 *   "{meseci}" for "{months}", which would print the braces or lose the value;
 * - empty translations.
 *
 * Files are written the way Crowdin exports them (two-space indent, unescaped
 * Unicode), so a sync shows only real changes. With --check nothing is
 * written and the exit code is 1 when a file would change, for CI. A file
 * that is not a JSON object stops the run with exit code 2.
 */
$check = in_array('--check', $argv, true);
$arguments = array_values(array_filter(array_slice($argv, 1), fn (string $argument): bool => $argument !== '--check'));
$directory = rtrim($arguments[0] ?? dirname(__DIR__, 2).'/lang', '/');

$english = read_messages("{$directory}/en.json");

if ($english === null) {
    fwrite(STDERR, "{$directory}/en.json is not a JSON object\n");
    exit(2);
}

$changed = [];

foreach (glob("{$directory}/*.json") as $path) {
    if (basename($path) === 'en.json') {
        continue;
    }

    $messages = read_messages($path);

    if ($messages === null) {
        fwrite(STDERR, basename($path)." is not a JSON object\n");
        exit(2);
    }

    $dropped = [];
    $clean = clean_messages($messages, $english, '', $dropped);
    $encoded = encode_messages($clean);

    if ($encoded === file_get_contents($path)) {
        continue;
    }

    $changed[] = basename($path);
    echo basename($path).': dropped '.count($dropped).($dropped === [] ? ' (formatting only)' : ': '.implode(', ', array_slice($dropped, 0, 5)).(count($dropped) > 5 ? ', ...' : ''))."\n";

    if (! $check) {
        file_put_contents($path, $encoded);
    }
}

if ($check && $changed !== []) {
    fwrite(STDERR, 'Not clean: '.implode(', ', $changed).". Run php .github/scripts/clean-translations.php\n");
    exit(1);
}

exit(0);

/**
 * @return array<string, mixed>|null
 */
function read_messages(string $path): ?array
{
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
}

/**
 * The translations that fit the English structure, in their own order.
 *
 * @param  array<string, mixed>  $messages
 * @param  array<string, mixed>  $english
 * @param  list<string>  $dropped  keys removed, dotted
 * @return array<string, mixed>
 */
function clean_messages(array $messages, array $english, string $prefix, array &$dropped): array
{
    $clean = [];

    foreach ($messages as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        $source = $english[$key] ?? null;

        if (is_array($value) && is_array($source)) {
            $nested = clean_messages($value, $source, $path, $dropped);

            if ($nested !== []) {
                $clean[$key] = $nested;
            }

            continue;
        }

        if (! is_string($value) || ! is_string($source) || trim($value) === '' || placeholders($value) !== placeholders($source)) {
            $dropped[] = $path;

            continue;
        }

        $clean[$key] = $value;
    }

    return $clean;
}

/**
 * @return list<string>
 */
function placeholders(string $message): array
{
    preg_match_all('/\{[^{}]*\}/', $message, $matches);
    $found = array_values(array_unique($matches[0]));
    sort($found);

    return $found;
}

/**
 * @param  array<string, mixed>  $messages
 */
function encode_messages(array $messages): string
{
    $json = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    // json_encode indents by four spaces; Crowdin exports two.
    return preg_replace_callback('/^( {4})+/m', fn (array $match): string => str_repeat(' ', intdiv(strlen($match[0]), 2)), $json)."\n";
}
