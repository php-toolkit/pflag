<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Contract;

/**
 * interface ParserInterface
 */
interface ParserInterface
{
    /**
     * @return array
     */
    public function getFlags(): array;

    /**
     * @return array
     */
    public function getRawArgs(): array;

    /**
     * @param array|null $flags
     *
     * @return bool
     */
    public function parse(?array $flags = null): bool;

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOpt(string $name): bool;

    /**
     * Get an option value by name
     *
     * @param string     $name
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getOpt(string $name, $default = null);

    /**
     * @param string|int $nameOrIndex
     *
     * @return bool
     */
    public function hasArg($nameOrIndex): bool;

    /**
     * Get an argument value by name
     *
     * @param string|int $nameOrIndex
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getArg($nameOrIndex, $default = null);

    /**
     * @return array
     */
    public function getOpts(): array;

    /**
     * @return array
     */
    public function getArgs(): array;
}
