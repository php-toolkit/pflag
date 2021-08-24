<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Traits;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Flag\Argument;
use function count;
use function is_string;

/**
 * Class CmdArgumentsTrait
 * - input arguments builder trait
 *
 * @package Toolkit\PFlag\Traits
 */
trait FlagArgumentsTrait
{
    /**
     * @var array [name => index]
     */
    private $name2index = [];

    /**
     * @var Argument[]
     */
    private $arguments = [];

    /**
     * Has array argument
     *
     * @var bool
     */
    private $arrayArg = false;

    /**
     * Has optional argument
     *
     * @var bool
     */
    private $optionalArg = false;

    /**
     * @param string     $name
     * @param string     $desc
     * @param bool       $required
     * @param string     $type The argument data type. (eg: 'string', 'array', 'mixed')
     * @param null|mixed $default
     * @param string     $alias
     */
    public function addArg(
        string $name,
        string $desc = '',
        bool $required = false,
        string $type = '',
        $default = null,
        string $alias = ''
    ): void {
        $argObj = Argument::new($name, $desc, $required, $default);
        $argObj->setType($type);
        $argObj->setAlias($alias);

        $this->addArgument($argObj);
    }

    /**
     * @param Argument $argument
     */
    public function addArgument(Argument $argument): void
    {
        $isArray  = $argument->isArray();
        $required = $argument->isRequired();

        $index = count($this->arguments);
        $argument->setIndex($index);

        $mark = $argument->getNameMark();

        // NOTICE: only allow one array argument and must be at last.
        if ($this->arrayArg && $isArray) {
            throw new FlagException("cannot add argument $mark after an array argument");
        }

        if ($this->optionalArg && $required) {
            throw new FlagException("cannot add a required argument $mark after an optional one");
        }

        $this->arrayArg    = $this->arrayArg || $isArray;
        $this->optionalArg = $this->optionalArg || !$required;

        // record index
        $this->name2index[$argument->getName()] = count($this->arguments);
        // append
        $this->arguments[] = $argument;
    }

    /**
     * @param string|int $nameOrIndex
     * @param null|mixed $default
     *
     * @return mixed|null
     */
    public function getArg($nameOrIndex, $default = null)
    {
        if ($arg = $this->getArgument($nameOrIndex)) {
            return $arg->getValue();
        }

        return $default;
    }

    /**
     * @param string|int $nameOrIndex
     *
     * @return Argument|null
     */
    public function getArgument($nameOrIndex): ?Argument
    {
        if (is_string($nameOrIndex)) {
            if (!isset($this->name2index[$nameOrIndex])) {
                throw new FlagException("flag argument '$nameOrIndex' is undefined");
            }

            $index = $this->name2index[$nameOrIndex];
        } else {
            $index = (int)$nameOrIndex;
        }

        return $this->arguments[$index] ?? null;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param Argument[] $arguments
     */
    public function setArguments(array $arguments): void
    {
        foreach ($arguments as $argument) {
            $this->addArgument($argument);
        }
    }

    /**
     * @return bool
     */
    public function hasOptionalArg(): bool
    {
        return $this->optionalArg;
    }

    /**
     * @return bool
     */
    public function hasArrayArg(): bool
    {
        return $this->arrayArg;
    }

    protected function resetArguments(): void
    {
        $this->name2index = [];
        $this->arguments  = [];
    }
}
