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
 * interface ValidatorInterface
 */
interface ValidatorInterface
{
    /**
     * Validate input value
     * - you can throw FlagException on fail
     *
     * Returns:
     * - bool `False` mark fail
     * - array [bool, value]
     *    - bool `False` mark fail
     *    - value filtered new value
     *
     * @param mixed  $value
     * @param string $name
     *
     * @return bool|array
     */
    public function __invoke(mixed $value, string $name): bool|array;

    /**
     * @return string
     */
    public function __toString(): string;
}
