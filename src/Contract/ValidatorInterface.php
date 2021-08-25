<?php declare(strict_types=1);

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
    public function __invoke($value, string $name);

    /**
     * @return string
     */
    public function __toString(): string;
}
