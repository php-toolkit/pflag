<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Class BaseTestCase
 * @package Inhere\ConsoleTest
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * @param callable $cb
     * @param mixed ...$args
     *
     * @return Throwable
     */
    protected function runAndGetException(callable $cb, ...$args): Throwable
    {
        try {
            $cb(...$args);
        } catch (Throwable $e) {
            return $e;
        }

        return new RuntimeException('NO ERROR');
    }
}
