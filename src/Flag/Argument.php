<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use function sprintf;

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
     * @param string $name
     */
    public function setName(string $name): void
    {
        if ($name) {
            parent::setName($name);
        }
    }

    /**
     * @return string
     */
    public function getNameMark(): string
    {
        $name = $this->name;
        $mark = $name ? "($name)" : '';

        return sprintf('#%d%s', $this->index, $mark);
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
