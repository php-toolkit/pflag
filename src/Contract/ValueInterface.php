<?php declare(strict_types=1);

namespace Toolkit\PFlag\Contract;

/**
 * interface ValueInterface
 */
interface ValueInterface
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function setValue($value);

    public function getValue();
}
