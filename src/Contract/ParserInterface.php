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
     * @psalm-return list<string>
     */
    public function getFlags(): array;

    /**
     * @return array
     * @psalm-return list<string>
     */
    public function getRawArgs(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * @return bool
     */
    public function isNotEmpty(): bool;

    /**
     * Add option
     *
     * @param string $name
     * @param string $shortcut
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed  $default
     * @param array  $moreInfo
     *
     * @psalm-param array{alias: string, showType: string} $moreInfo
     *
     * @return self
     */
    public function addOpt(
        string $name,
        string $shortcut,
        string $desc,
        string $type = '',
        bool $required = false,
        $default = null,
        array $moreInfo = []
    ): self;

    /**
     * Add an argument
     *
     * @param string $name
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed  $default
     * @param array  $moreInfo
     *
     * @psalm-param array{showType: string} $moreInfo
     *
     * @return self
     */
    public function addArg(
        string $name,
        string $desc,
        string $type = '',
        bool $required = false,
        $default = null,
        array $moreInfo = []
    ): self;

    /**
     * @param array|null $flags If NULL, will parse the $_SERVER['argv]
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
     * @psalm-return array<string, mixed>
     */
    public function getOpts(): array;

    /**
     * @return array
     */
    public function getArgs(): array;

    /**
     * @return array
     * @psalm-return array<string, string>
     */
    public function getOptSimpleDefines(): array;

    /**
     * @return bool
     */
    public function isLocked(): bool;

    public function lock(): void;

    public function unlock(): void;
}
