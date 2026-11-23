# WHMCS UTF-8 to ASCII Transliteration Hook

A lightweight PHP hook that converts Arabic and other UTF-8 text to printable ASCII in self-hosted WHMCS installations. It is useful when a domain registrar, payment gateway or other external integration requires ASCII contact or billing details and receives those details through WHMCS’s transliteration mechanism.

For example, it can convert `محمد الشناق` to `mhmd alshnaq` and `شارع التحلية ١٢` to `shar althlyt 12`.

## How it works

WHMCS discovers `hook_transliterate()` by name in `includes/hooks/` and calls it in place of its default transliteration function. The hook delegates conversion to `whmcs_ascii_transliterate()`.

The function preserves printable ASCII input as supplied. For other input, it normalises whitespace, removes bidirectional formatting marks and applies the `Any-Latin; Latin-ASCII` rules from ICU through PHP’s Intl extension. If conversion fails or produces an empty or non-ASCII result, the hook returns the original input.

The override applies wherever WHMCS uses its transliteration mechanism. Its effect on a specific registrar, payment gateway or provisioning module depends on how that module obtains its input. The hook transforms the value passed to it and leaves stored customer details intact.

## Scope and customer-approved registrant details

WHMCS Support explained that custom transliteration applies to module requests that use client details through WHMCS’s transliteration mechanism. This includes domain registrar modules and server or provisioning modules.

Automatic transliteration may produce a different spelling from the one the customer uses in Latin script. If the customer needs to approve the domain registrant details, ask them to provide or select a registrant contact with their preferred Latin-script details at checkout.

## Requirements

- A self-hosted WHMCS installation. This hook follows the WHMCS 9.1 custom transliteration documentation.
- PHP 8.0 or later for the hook and the test runner. Use a PHP version supported by your WHMCS release.
- The PHP `intl` extension in the runtime that runs WHMCS and, for standalone testing, in the PHP CLI.
- UTF-8 input.

## Installation

1. Check `includes/hooks/` for an existing definition of `hook_transliterate()`. Keep a single definition active, including when replacing an older copy of this hook.
2. Copy `whmcs_ascii_transliteration.php` to your WHMCS installation at `includes/hooks/whmcs_ascii_transliteration.php`.
3. Check the installed file from the root of your WHMCS installation:

   ```bash
   php -l includes/hooks/whmcs_ascii_transliteration.php
   ```

WHMCS loads the function automatically. No `add_hook()` registration is required.

**`whmcs_ascii_transliteration.php` is the only file required in WHMCS.** You can keep `test-transliteration.php`, `arabic-samples.txt` and `non-latin-samples.txt` on your local machine.

To remove the override, move `whmcs_ascii_transliteration.php` out of `includes/hooks/`. WHMCS will resume using its default transliteration function.

## Testing outside WHMCS

Keep the hook, the test runner and whichever sample files you want to use in the same directory. The CLI runner loads the same hook file used by WHMCS and prints the original and converted text for each sample.

```bash
php -l whmcs_ascii_transliteration.php

# Run the default Arabic sample set.
php test-transliteration.php

# Run samples from 19 languages that use non-Latin scripts.
php test-transliteration.php non-latin-samples.txt

# Run your own UTF-8 text file, with one sample per line.
php test-transliteration.php /path/to/my-samples.txt
```

To test a hook file in a different directory, pass its path as the second argument:

```bash
php test-transliteration.php arabic-samples.txt /path/to/whmcs_ascii_transliteration.php
```

The runner shows the PHP and ICU versions, the hook file it loaded and the result for each line. It skips empty lines and checks that each output is printable ASCII and stays the same on a second pass.

| Exit code | Meaning |
| --- | --- |
| `0` | All tested lines produced stable printable ASCII. |
| `1` | At least one output was empty, contained non-ASCII characters or changed on a second pass. |
| `2` | A required file or dependency was unavailable, or the sample file contained no non-empty lines. |

## Verifying an integration in WHMCS

After installation, open the WHMCS Admin Area and verify that it loads normally. Then run the operation you want to check using representative contact details. For a previously failed domain registration, open the domain in the client’s **Domains** tab, confirm its registrar and use **Register** under **Registrar Commands**. Confirm the result in both WHMCS and the registrar’s portal.

The standalone test verifies the hook’s output. An integration test verifies which values reach the provider and whether the provider accepts them.

## Limitations

- Transliteration approximates spelling. Prefer the customer’s approved Latin-script spelling when it is available.
- Results can vary by ICU version and writing system. The multilingual sample set includes inputs that can remain unchanged and therefore produce exit code `1`.
- Printable ASCII can include punctuation. Each provider may apply its own rules for allowed characters, field lengths and required values.

## Documentation

- [WHMCS 9.1 custom transliteration documentation](https://docs.whmcs.com/9-1/customization/transliteration/)

## Author and licence

Written by **Mohammed AlShannaq**.

Copyright © 2026 **Extendy LTD**. Released under the [MIT Licence](https://opensource.org/license/mit).