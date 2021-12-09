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
 * Interface FlagInterface
 *
 * @package Toolkit\PFlag\Contract
 */
interface FlagInterface
{
    public function init(): void;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string
     */
    public function getHelpName(): string;

    /**
     * @param bool $forHelp
     *
     * @return string
     */
    public function getDesc(bool $forHelp = false): string;

    /**
     * Get the flag value
     *
     * @return mixed
     */
    public function getValue(): mixed;

    /**
     * @param mixed $value
     */
    public function setValue(mixed $value): void;

    /**
     * @param mixed $value
     */
    public function setTrustedValue(mixed $value): void;

    /**
     * @return bool
     */
    public function isArray(): bool;

    /**
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * @return bool
     */
    public function isOptional(): bool;

    /**
     * @return string
     */
    public function getKind(): string;
}
