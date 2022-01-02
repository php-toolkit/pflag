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
 * interface ValueInterface
 */
interface ValueInterface
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function setValue(mixed $value): mixed;

    public function getValue();
}
