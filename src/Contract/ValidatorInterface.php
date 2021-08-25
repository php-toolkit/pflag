<?php declare(strict_types=1);

namespace Toolkit\PFlag\Contract;

/**
 * interface ValidatorInterface
 */
interface ValidatorInterface
{
    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function __invoke($value, string $name): bool;

    /**
     * @return string
     */
    public function __toString(): string;
}
