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
     * Get the flag value
     *
     * @return mixed
     */
    public function getValue();
}
