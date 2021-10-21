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
     * @param mixed  $default
     * @param array  $moreInfo
     *
     * @psalm-param array{alias: string, helpType: string} $moreInfo
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
     * @psalm-param array{helpType: string} $moreInfo
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
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getOpt(string $name, $default = null);

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
    public function setOpt(string $name, $value): void;

    /**
     * Set trusted option value, will not format and validate value.
     *
     * @param mixed $value
     */
    public function setTrustedOpt(string $name, $value): void;

    /**
     * Whether defined the argument
     *
     * @param string|int $nameOrIndex
     *
     * @return bool
     */
    public function hasArg($nameOrIndex): bool;

    /**
     * Whether input argument
     *
     * @param string|int $nameOrIndex
     *
     * @return bool
     */
    public function hasInputArg($nameOrIndex): bool;

    /**
     * @param string|int $nameOrIndex
     *
     * @return int Will return -1 if arg not exists
     */
    public function getArgIndex($nameOrIndex): int;

    /**
     * @param string|int $nameOrIndex
     *
     * @return array
     * @see FlagsParser::DEFINE_ITEM
     */
    public function getArgDefine($nameOrIndex): array;

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
     * Get an argument value by name, will throw exception on not input
     *
     * @param string|int $nameOrIndex
     *
     * @return mixed
     */
    public function getMustArg($nameOrIndex, string $errMsg = '');

    /**
     * Set trusted argument value, will not format and validate value.
     *
     * @param string|int $nameOrIndex
     * @param mixed $value
     *
     * @return mixed
     */
    public function setArg($nameOrIndex, $value): void;

    /**
     * Set trusted argument value, will not format and validate value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedArg(string $name, $value): void;

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
     * Get args help data
     *
     * @return array
     * @psalm-return array<string, string>
     */
    public function getArgsHelpData(): array;

    /**
     * Get opts help data
     *
     * @return array
     * @psalm-return array<string, string>
     */
    public function getOptsHelpData(): array;

    public function lock(): void;

    public function unlock(): void;

    /**
     * @return bool
     */
    public function isLocked(): bool;

}
