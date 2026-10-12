# WilyCode File Cache

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/cache-file/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/cache-file/actions/workflows/tests.yml)

**A lightweight PSR-16 file cache for PHP 8.4+.** Store application data on disk with atomic writes, optional expiration, and a simple, standard API.

> **WilyCode PHP Ecosystem**
>
> This library is part of **[WilyCode](https://github.com/wilycode/wilycode)** — a collection of independent, reusable PHP components. Each package has its own repository, releases, and Composer installation.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Features

- Implements [`psr/simple-cache`](https://www.php-fig.org/psr/psr-16/) (PSR-16).
- Stores entries in hashed subdirectories, away from readable cache-key names.
- Writes files through a temporary file and an atomic rename.
- Supports default and per-entry TTLs (`int` seconds or `DateInterval`).
- Provides single-key and bulk cache operations.
- Removes expired or malformed entries when they are read.
- Requires no database, server daemon, or framework.

## Requirements

- PHP 8.4 or newer.
- A writable directory dedicated to cache files.
- Composer installs `psr/simple-cache:^3.0` automatically.

## Installation

```bash
composer require wilycode/cache-file
```

## Quick start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WilyCode\FileCache\FileCache;

$cache = new FileCache(
    directory: __DIR__ . '/var/cache',
    defaultTtl: 3600, // 1 hour
);

$cache->set('article.15', ['title' => 'Hello'], ttl: 300);

$article = $cache->get('article.15');
// ['title' => 'Hello']

$fallback = $cache->get('missing', 'Not found');
// 'Not found'

$exists = $cache->has('article.15'); // true
$cache->delete('article.15');
```

The cache directory and its hashed subdirectories are created automatically when
an entry is written. Use a dedicated, writable path **outside your public web root**.

## Expiration (TTL)

A TTL controls how long an entry remains valid:

```php
use DateInterval;

$cache->set('short', 'value', ttl: 60);                     // 60 seconds
$cache->set('session-data', ['active' => true], ttl: new DateInterval('PT10M'));
$cache->set('remove-me', 'value', ttl: 0);                // deletes this key
```

- If no TTL is provided, the cache uses the constructor's `defaultTtl`.
- With no default TTL (`null`), entries do not expire automatically.
- An integer TTL of `0` or less removes the entry, as required by PSR-16.
- Expired entries return the supplied default value and are removed on access.

> **Note:** Passing `null` to `set()` does not override a non-null default TTL;
> it uses the configured default.

## Working with multiple entries

```php
$cache->setMultiple(['one' => 1, 'two' => 2], ttl: 300);

$values = $cache->getMultiple(['one', 'two', 'missing'], default: null);
// ['one' => 1, 'two' => 2, 'missing' => null]

$cache->deleteMultiple(['one', 'two']);
$cache->clear(); // removes all contents of the dedicated cache directory
```

Cache keys must be strings and cannot be empty or contain PSR-16 reserved
characters (`{}()/\@:`). Invalid keys throw
`WilyCode\FileCache\Exception\InvalidArgumentException`.

## Storage and safety

Values are stored using PHP `serialize()` / `unserialize()` so that arrays and
PHP objects can be cached. **Only use this cache with trusted applications and
trusted cache files.** PHP deserialization is unsafe for attacker-controlled
content. Do not use a shared writable directory accessible by untrusted users.

- Keep the cache directory outside `public/` and restrict filesystem permissions.
- `clear()` removes **every file and subdirectory** inside the configured cache
  directory. Never point it at a directory holding unrelated files.
- Atomic renaming helps prevent readers from seeing partially written entries;
  it is not a distributed locking mechanism.
- Avoid storing secrets or sensitive personal data unless your filesystem and
  retention policies protect them appropriately.

## Development

```bash
composer install
composer validate --strict
composer test
```

Tests run automatically in [GitHub Actions](https://github.com/wilycode/cache-file/actions).
Tests are included in the Git repository but excluded from standard Composer
distribution archives.

## Contributing

Bug reports and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md)
and [GitHub Issues](https://github.com/wilycode/cache-file/issues).

## License

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).
