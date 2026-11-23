<?php

//used for test only with cli only
//Do not upload thie file to production server
//if you found this file in production server, please delete it 

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$samplesPath = $argv[1] ?? __DIR__ . '/arabic-samples.txt';
$hookPath = $argv[2] ?? __DIR__ . '/whmcs_ascii_transliteration.php';

foreach ([$samplesPath, $hookPath] as $path) {
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "Cannot read file: {$path}\n");
        exit(2);
    }
}

// Simulate the WHMCS bootstrap for standalone CLI testing.
if (!defined('WHMCS')) {
    define('WHMCS', true);
}

require $hookPath;

if (!is_callable('hook_transliterate') || !is_callable([Transliterator::class, 'create'])) {
    fwrite(STDERR, "The WHMCS hook and PHP Intl Transliterator must be available.\n");
    exit(2);
}

$samples = file($samplesPath, FILE_IGNORE_NEW_LINES);
if ($samples === false) {
    fwrite(STDERR, "Could not load samples: {$samplesPath}\n");
    exit(2);
}

if (isset($samples[0]) && str_starts_with($samples[0], "\xEF\xBB\xBF")) {
    $samples[0] = substr($samples[0], 3);
}

printf("PHP %s | ICU %s\nHook: %s\n\n", PHP_VERSION, INTL_ICU_VERSION, realpath($hookPath));

$checked = 0;
$failed = 0;
foreach ($samples as $index => $input) {
    if ($input === '') {
        continue;
    }

    $checked++;
    $output = hook_transliterate($input);
    $ascii = preg_match('/\A[\x20-\x7E]+\z/', $output) === 1;
    $stable = $ascii && hook_transliterate($output) === $output;
    $failed += (int) !$stable;

    printf("Line %d [%s]\n", $index + 1, match (true) {
        !$ascii => 'OUTPUT NOT ASCII OR EMPTY',
        !$stable => 'OUTPUT CHANGED ON SECOND PASS',
        $output === $input => 'ASCII UNCHANGED',
        default => 'CONVERTED TO ASCII',
    });
    printf("  Input  %s\n  Output %s\n\n", renderSample($input), renderSample($output));
}

if ($checked === 0) {
    fwrite(STDERR, "No non-empty samples were found.\n");
    exit(2);
}

printf("%d/%d lines produced stable ASCII output.\n", $checked - $failed, $checked);
exit($failed === 0 ? 0 : 1);

function renderSample(string $text): string
{
    return preg_match('//u', $text) === 1
        ? json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        : 'Invalid UTF-8 (hex: ' . bin2hex($text) . ')';
}
