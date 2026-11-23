<?php
/**
 * WHMCS UTF-8 to ASCII Transliteration Hook
 *
 * Converts Arabic and other UTF-8 text, such as customer names, company
 * names, and addresses, to printable ASCII. This helps when third-party
 * systems, including domain registrars and payment gateways, require
 * Latin-script contact or billing details.
 *
 * Place this file in the WHMCS includes/hooks/ directory. WHMCS calls
 * hook_transliterate() in place of its default transliteration function.
 * The hook applies to integrations that use this WHMCS transliteration path.
 *
 * Non-ASCII conversion requires the PHP Intl extension. Printable ASCII
 * is preserved as received. If conversion fails or the result is not
 * printable ASCII, the hook returns the original input.
 *
 * @author Mohammed AlShannaq
 * @copyright 2026 Extendy LTD
 * SPDX-License-Identifier: MIT
 */

if (!defined('WHMCS'))
    die('You cannot access this file directly.');

/**
 * WHMCS entry point for custom transliteration.
 *
 * WHMCS looks for this function by name and calls it in place of its
 * default transliteration function. Keep the name hook_transliterate
 * so WHMCS can discover it. No add_hook() registration is required.
 *
 * Delegates conversion to whmcs_ascii_transliterate() and returns the
 * original input if the conversion fails.
 *
 * @param string $string The text supplied by WHMCS for transliteration.
 * @return string The converted text, or the original input on failure.
 * @see https://docs.whmcs.com/9-1/customization/transliteration/ WHMCS 9.1 custom transliteration documentation.
 */
function hook_transliterate(string $string): string
{
    return whmcs_ascii_transliterate($string) ?? $string;
}

/**
 * Converts UTF-8 text to printable ASCII using PHP Intl and ICU.
 *
 * Preserves printable ASCII input as received. For other input,
 * normalizes whitespace, removes bidirectional formatting marks,
 * and applies ICU's Any-Latin and Latin-ASCII transliteration rules.
 *
 * Returns null if the input is invalid UTF-8, the transliterator is
 * unavailable, or conversion does not produce non-empty printable ASCII.
 * The calling hook uses null to fall back to the original input.
 *
 * @param string $text The input text to transliterate.
 * @return string|null The printable ASCII result, or null on failure.
 */
function whmcs_ascii_transliterate(string $text): ?string
{
    // Preserve printable ASCII exactly as received.
    if (preg_match('/\A[\x20-\x7E]*\z/', $text) === 1) {
        return $text;
    }

    // The WHMCS installation is expected to use UTF-8.
    if (preg_match('//u', $text) !== 1 || !is_callable([\Transliterator::class, 'create'])) {
        return null;
    }

    // Preserve word boundaries and remove bidirectional formatting marks.
    $normalized = preg_replace('/[\s\p{Z}]+/u', ' ', $text);
    $normalized = preg_replace('/\p{Bidi_Control}/u', '', $normalized ?? $text);

    try {
        static $transliterator = null;
        $transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII');
        $converted = $transliterator?->transliterate($normalized ?? $text);
    } catch (\IntlException $exception) {
        return null;
    }

    if (!is_string($converted)) {
        return null;
    }

    // Omit the standalone Latin markers ICU uses for ayn and hamza.
    $converted = str_replace(['ʿ', 'ʾ'], '', $converted);
    $converted = trim(preg_replace('/ +/', ' ', $converted) ?? $converted, ' ');

    return preg_match('/\A[\x20-\x7E]+\z/', $converted) === 1
        ? $converted
        : null;
}