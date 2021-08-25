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
use Toolkit\PFlag\Flag\Option;

/**
 * Class InputOptions
 * - input options builder
 *
 * @package Inhere\Console\IO\Input
 */
trait FlagOptionsTrait
{
    /**
     * The defined options on init.
     *
     * ```php
     * [
     *  name => Option,
     * ]
     * ```
     *
     * @var Option[]
     */
    private $defined = [];

    /**
     * The matched options on runtime
     *
     * ```php
     * [
     *  name => Option,
     * ]
     * ```
     *
     * @var Option[]
     */
    private $matched = [];

    /**
     * @param string $name
     * @param string $shorts
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed  $default
     * @param string $alias
     */
    public function addOpt(
        string $name,
        string $shorts,
        string $desc,
        string $type = '',
        bool $required = false,
        $default = null,
        string $alias = ''
    ): void {
        /** @var Option $opt */
        $opt = Option::new($name, $desc, $type, $required, $default);
        $opt->setAlias($alias);
        $opt->setShortcut($shorts);

        $this->addOption($opt);
    }

    /**
     * @param Option $option
     */
    public function addOption(Option $option): void
    {
        $name = $option->getName();

        if (isset($this->defined[$name])) {
            throw new FlagException('cannot repeat add option: ' . $name);
        }

        if ($alias = $option->getAlias()) {
            $this->setAlias($name, $alias, true);
        }

        if ($ss = $option->getShorts()) {
            foreach ($ss as $s) {
                $this->setAlias($name, $s, true);
            }
        }

        $option->init();

        // add to defined
        $this->defined[$name] = $option;
    }

    /**
     * @param Option[] $options
     */
    public function addOptions(array $options): void
    {
        foreach ($options as $option) {
            $this->addOption($option);
        }
    }

    /**
     * @param Option $option
     */
    public function addMatched(Option $option): void
    {
        $name = $option->getName();
        // add to matched
        $this->matched[$name] = $option;
    }

    /**
     * @param string     $name
     * @param null|mixed $default
     *
     * @return mixed|null
     */
    public function getOpt(string $name, $default = null)
    {
        if ($arg = $this->getOption($name)) {
            return $arg->getValue();
        }

        if ($default === null && ($arg = $this->getDefinedOption($name))) {
            return $arg->hasDefault() ? $arg->getDefault() : $default;
        }

        return $default;
    }

    /**
     * @return array
     */
    public function getOpts(): array
    {
        $opts = [];
        foreach ($this->matched as $name => $option) {
            $opts[$name] = $option->getValue();
        }

        return $opts;
    }

    /**
     * @param string $name
     *
     * @return Option|null
     */
    public function getOption(string $name): ?Option
    {
        return $this->matched[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return Option|null
     */
    public function getDefinedOption(string $name): ?Option
    {
        return $this->defined[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasDefined(string $name): bool
    {
        return isset($this->defined[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMatched(string $name): bool
    {
        return isset($this->matched[$name]);
    }

    /**
     * @return Option[]
     */
    public function getDefinedOptions(): array
    {
        return $this->defined;
    }

    /**
     * @return Option[]
     */
    public function getOptions(): array
    {
        return $this->matched;
    }

    /**
     * @return Option[]
     */
    public function getMatchedOptions(): array
    {
        return $this->matched;
    }
}
