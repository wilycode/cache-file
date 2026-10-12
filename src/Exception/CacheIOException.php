<?php

declare(strict_types=1);

namespace WilyCode\FileCache\Exception;

/**
 * IO-level errors (filesystem permissions, missing directories, etc.).
 */
final class CacheIOException extends \RuntimeException
{
}
