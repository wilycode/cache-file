<?php

declare(strict_types=1);

namespace WilyCode\FileCache\Exception;

/**
 * Thrown when a cache key/argument is invalid.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{
}
