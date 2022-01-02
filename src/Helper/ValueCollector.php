<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Helper;

use Toolkit\PFlag\FlagsParser;

/**
 * class ValueCollector
 * - collect value by start i-shell env.
 */
class ValueCollector
{
    /**
     * @return $this
     */
    public function new(): self
    {
        return new self();
    }

    /**
     * @param FlagsParser $fs
     */
    public function collect(FlagsParser $fs): void
    {
    }
}
