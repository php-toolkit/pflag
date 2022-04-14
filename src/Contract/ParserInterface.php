<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Contract;

use Toolkit\PFlag\FlagsParser;

/**
 * interface ParserInterface
 */
interface ParserInterface
{
    public const KIND_OPT = 'option';

    public const KIND_ARG = 'argument';

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
     * @return bool
     */
    public function hasShortOpts(): bool;

    /**
     * Add option
     *
     * @param string $name
     * @param string $shortcut
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed|null $default
     * @param array{aliases: array, helpType: string} $moreInfo
     *
     * @return self
     */
    public function addOpt(
        string $name,
        string $shortcut,
        string $desc,
        string $type = '',
        bool $required = false,
        mixed $default = null,
        array $moreInfo = []
    ): static;

    /**
     * Add an argument
     *
     * @param string $name
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed|null $default
     * @param array{helpType: string, validator: callable|ValidatorInterface}  $moreInfo
     *
     * @return self
     */
    public function addArg(
        string $name,
        string $desc,
        string $type = '',
        bool $required = false,
        mixed $default = null,
        array $moreInfo = []
    ): static;

    /**
     * @param array|null $flags If NULL, will parse the $_SERVER['argv]
     *
     * @return bool
     */
    public function parse(?array $flags = null): bool;

    /**
     * Whether defined the option
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasOpt(string $name): bool;

    /**
     * Whether input argument
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasInputOpt(string $name): bool;

    /**
     * Get an option value by name
     *
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOpt(string $name, mixed $default = null): mixed;

    /**
     * Must get an option value by name, will throw exception on not input
     *
     * @param string $name
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustOpt(string $name, string $errMsg = ''): mixed;

    /**
     * @param string $name
     *
     * @return array
     * @see FlagsParser::DEFINE_ITEM
     */
    public function getOptDefine(string $name): array;

    /**
     * Set option value, will format and validate value.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return mixed
     */
    public function setOpt(string $name, mixed $value): void;

    /**
     * Set trusted option value, will not format and validate value.
     *
     * @param mixed $value
     */
    public function setTrustedOpt(string $name, mixed $value): void;

    /**
     * Whether defined the argument
     *
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasArg(int|string $nameOrIndex): bool;

    /**
     * Whether input argument
     *
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasInputArg(int|string $nameOrIndex): bool;

    /**
     * @param int|string $nameOrIndex
     *
     * @return int Will return -1 if arg not exists
     */
    public function getArgIndex(int|string $nameOrIndex): int;

    /**
     * @param int|string $nameOrIndex
     *
     * @return array
     * @see FlagsParser::DEFINE_ITEM
     */
    public function getArgDefine(int|string $nameOrIndex): array;

    /**
     * Get an argument value by name
     *
     * @param int|string $nameOrIndex
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getArg(int|string $nameOrIndex, mixed $default = null): mixed;

    /**
     * Must get an argument value by name, will throw exception on not input
     *
     * @param int|string $nameOrIndex
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustArg(int|string $nameOrIndex, string $errMsg = ''): mixed;

    /**
     * Set trusted argument value, will not format and validate value.
     *
     * @param int|string $nameOrIndex
     * @param mixed $value
     *
     * @return mixed
     */
    public function setArg(int|string $nameOrIndex, mixed $value): void;

    /**
     * Set trusted argument value, will not format and validate value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedArg(string $name, mixed $value): void;

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
     * Get args help lines data
     *
     * ```php
     * [
     *  helpName => format desc,
     * ]
     * ```
     *
     * @return array
     * @psalm-return array<string, string>
     */
    public function getArgsHelpLines(): array;

    /**
     * Get opts help lines data
     *
     * ```php
     * [
     *  helpName => format desc,
     * ]
     * ```
     *
     * @return array
     * @psalm-return array<string, string>
     */
    public function getOptsHelpLines(): array;

    public function lock(): void;

    public function unlock(): void;

    /**
     * @return bool
     */
    public function isLocked(): bool;
}
