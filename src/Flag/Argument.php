<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use Inhere\Console\IO\Input;

/**
 * Class InputArgument
 * - definition a input argument
 *
 * @package Inhere\Console\IO\Input
 */
class Argument extends AbstractFlag
{
    /**
     * The argument position
     *
     * @var int
     */
    private $index = 0;

    /**
     * @return bool
     */
    public function isArray(): bool
    {
        return $this->hasMode(Input::ARG_IS_ARRAY);
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->hasMode(Input::ARG_OPTIONAL);
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->hasMode(Input::ARG_REQUIRED);
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @param int $index
     */
    public function setIndex(int $index): void
    {
        $this->index = $index;
    }
}
