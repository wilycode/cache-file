<?php

declare(strict_types=1);

namespace WilyCode\FileCache;

use DateInterval;
use DateTimeImmutable;
use FilesystemIterator;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WilyCode\FileCache\Exception\CacheIOException;
use WilyCode\FileCache\Exception\InvalidArgumentException;

/**
 * PSR-16 cache using atomic files split into hash-prefix directories.
 */
final class FileCache implements CacheInterface
{
    private readonly string $directory;

    public function __construct(
        string $directory,
        private readonly null|int|DateInterval $defaultTtl = null,
        private readonly int $directoryMode = 0775,
        private readonly int $fileMode = 0664,
    ) {
        $directory = rtrim($directory, " \t\n\r\0\x0B/\\");
        if ($directory === '') {
            throw new InvalidArgumentException('Cache directory path cannot be empty.');
        }

        if (is_int($this->defaultTtl) && $this->defaultTtl < 0) {
            throw new InvalidArgumentException('Default TTL cannot be negative.');
        }

        $this->directory = $directory;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry === null ? $default : $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->assertValidKey($key);

        $effectiveTtl = $ttl ?? $this->defaultTtl;
        if (is_int($effectiveTtl) && $effectiveTtl <= 0) {
            return $this->delete($key);
        }

        $filename = $this->filename($key);
        $this->createDirectory(dirname($filename));

        $payload = serialize([
            'expiresAt' => $this->expiryTimestamp($effectiveTtl),
            'value' => $value,
        ]);

        return $this->atomicWrite($filename, $payload);
    }

    public function delete(string $key): bool
    {
        $this->assertValidKey($key);

        $filename = $this->filename($key);

        return !is_file($filename) || @unlink($filename);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $success = true;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $node) {
            $removed = $node->isDir()
                ? @rmdir($node->getPathname())
                : @unlink($node->getPathname());

            $success = $removed && $success;
        }

        return $success;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /** @param iterable<mixed, mixed> $values */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Cache key must be a string.');
            }

            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /** @return array{expiresAt: ?int, value: mixed}|null */
    private function read(string $key): ?array
    {
        $this->assertValidKey($key);

        $filename = $this->filename($key);
        if (!is_file($filename)) {
            return null;
        }

        $payload = @file_get_contents($filename);
        if (!is_string($payload) || $payload === '') {
            @unlink($filename);
            return null;
        }

        $entry = @unserialize($payload, ['allowed_classes' => true]);
        if (
            !is_array($entry)
            || !array_key_exists('expiresAt', $entry)
            || !array_key_exists('value', $entry)
            || ($entry['expiresAt'] !== null && !is_int($entry['expiresAt']))
        ) {
            @unlink($filename);
            return null;
        }

        if ($entry['expiresAt'] !== null && $entry['expiresAt'] <= time()) {
            @unlink($filename);
            return null;
        }

        /** @var array{expiresAt: ?int, value: mixed} $entry */
        return $entry;
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty.');
        }

        if (strpbrk($key, '{}()/\\@:') !== false) {
            throw new InvalidArgumentException('Cache key contains a reserved character: {}()/\\@:');
        }
    }

    private function filename(string $key): string
    {
        $hash = hash('sha256', $key);

        return $this->directory
            . DIRECTORY_SEPARATOR . substr($hash, 0, 2)
            . DIRECTORY_SEPARATOR . substr($hash, 2);
    }

    private function createDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, $this->directoryMode, true) && !is_dir($directory)) {
            throw new CacheIOException('Unable to create cache directory: ' . $directory);
        }
    }

    private function expiryTimestamp(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return time() + $ttl;
        }

        return new DateTimeImmutable()->add($ttl)->getTimestamp();
    }

    private function atomicWrite(string $filename, string $payload): bool
    {
        $temporary = $filename . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            return false;
        }

        $success = false;

        try {
            if (!@flock($handle, LOCK_EX)) {
                return false;
            }

            $length = strlen($payload);
            $offset = 0;
            while ($offset < $length) {
                $written = @fwrite($handle, substr($payload, $offset));
                if (!is_int($written) || $written < 1) {
                    return false;
                }
                $offset += $written;
            }

            if (!@fflush($handle)) {
                return false;
            }

            $success = true;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);

            if (!$success) {
                @unlink($temporary);
            }
        }

        @chmod($temporary, $this->fileMode);

        if (!@rename($temporary, $filename)) {
            @unlink($temporary);
            return false;
        }

        return true;
    }

}
