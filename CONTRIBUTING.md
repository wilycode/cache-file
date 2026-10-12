# Contributing to WilyCode File Cache

Thanks for your interest in contributing.

## Before opening a pull request

1. Open an issue to discuss significant behavior or API changes.
2. Keep changes focused and compatible with the PSR-16 cache interface.
3. Add or update PHPUnit tests for any behavior changes.
4. Run the checks:

   ```bash
   composer install
   composer validate --strict
   composer test
   ```

5. Include a short explanation of what changed and why.

## Security

Please avoid posting credentials or sensitive cached data in issues. Do not use
untrusted cache files as input: this package stores values with PHP serialization.

## WilyCode ecosystem

See the [WilyCode PHP ecosystem](https://github.com/wilycode/wilycode) for the
full collection of independently versioned packages.
