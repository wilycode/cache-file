<?php

declare(strict_types=1);

namespace WilyCode\FileCache\Tests;

use DateInterval;
use PHPUnit\Framework\TestCase;
use WilyCode\FileCache\Exception\InvalidArgumentException;
use WilyCode\FileCache\FileCache;

final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wilycode-file-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);

        parent::tearDown();
    }

    public function test_set_and_get_roundtrip(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertSame('bar', $cache->get('foo'));
        $this->assertTrue($cache->has('foo'));
    }

    public function test_get_returns_default_when_missing(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $this->assertSame('def', $cache->get('missing', 'def'));
        $this->assertFalse($cache->has('missing'));
    }

    public function test_expired_item_returns_default_and_is_pruned(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $cache->set('short', 'value', 1);
        sleep(2);

        $this->assertSame('fallback', $cache->get('short', 'fallback'));
        $this->assertFalse($cache->has('short'));
    }

    public function test_dateinterval_ttl_is_supported(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $this->assertTrue($cache->set('di', 123, new DateInterval('PT2S')));
        $this->assertSame(123, $cache->get('di'));

        sleep(3);
        $this->assertNull($cache->get('di'));
    }

    public function test_get_multiple_and_set_multiple(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $this->assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));
        $this->assertSame(['a' => 1, 'b' => 2], $cache->getMultiple(['a', 'b']));
    }

    public function test_clear_removes_nested_directories(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $cache->set('a', 'x');
        $cache->set('b', 'y');
        $cache->set('c', 'z');

        $this->assertTrue(is_dir($this->dir));
        $this->assertTrue($cache->clear());

        // Directory should exist (or not) but must be empty.
        if (is_dir($this->dir)) {
            $files = array_values(array_diff(scandir($this->dir) ?: [], ['.', '..']));
            $this->assertSame([], $files);
        }
    }

    public function test_invalid_key_throws(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 3600);

        $this->expectException(InvalidArgumentException::class);
        $cache->set('bad:key', 'x');
    }

    public function test_cached_null_is_not_treated_as_a_missing_key(): void
    {
        $cache = new FileCache($this->dir);

        $this->assertTrue($cache->set('nullable', null));
        $this->assertTrue($cache->has('nullable'));
        $this->assertNull($cache->get('nullable', 'fallback'));
    }

    public function test_zero_and_negative_ttl_delete_an_existing_entry(): void
    {
        $cache = new FileCache($this->dir);

        $this->assertTrue($cache->set('key', 'first'));
        $this->assertTrue($cache->set('key', 'second', 0));
        $this->assertFalse($cache->has('key'));

        $this->assertTrue($cache->set('key', 'third'));
        $this->assertTrue($cache->set('key', 'fourth', -1));
        $this->assertFalse($cache->has('key'));
    }

    public function test_explicit_default_ttl_of_zero_does_not_store_entries(): void
    {
        $cache = new FileCache($this->dir, defaultTtl: 0);

        $this->assertTrue($cache->set('key', 'value'));
        $this->assertFalse($cache->has('key'));
    }

    public function test_corrupted_cache_file_is_removed_on_read(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('broken', 'before');

        $hash = hash('sha256', 'broken');
        $path = $this->dir . DIRECTORY_SEPARATOR . substr($hash, 0, 2)
            . DIRECTORY_SEPARATOR . substr($hash, 2);

        $this->assertSame('before', $cache->get('broken'));
        $this->assertNotFalse(file_put_contents($path, 'not a serialized cache entry'));
        $this->assertSame('fallback', $cache->get('broken', 'fallback'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_set_multiple_rejects_non_string_keys(): void
    {
        $cache = new FileCache($this->dir);

        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple([0 => 'invalid']);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $node) {
            $path = $node->getPathname();
            if ($node->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
