<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

/**
 * Class InputArguments
 * - input arguments builder
 *
 * @package Toolkit\PFlag\Flag
 */
class Arguments
{
    /**
     * @var array
     */
    private $arguments = [];

    /**
     * @param string      $name
     * @param int|null    $mode
     * @param string|null $type The argument data type. (eg: 'string', 'array', 'mixed')
     * @param string      $description
     * @param null        $default
     * @param null        $alias
     */
    public function add(
        string $name,
        int $mode = null,
        string $type = null,
        string $description = '',
        $default = null,
        $alias = null
    ): void {
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
