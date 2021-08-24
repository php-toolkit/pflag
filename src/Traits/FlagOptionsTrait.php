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
     * @var Option[]
     */
    private $defined = [];

    /**
     * The matched options on runtime
     *
     * @var Option[]
     */
    private $matched = [];

    /**
     * @param string $name
     * @param string $shorts
     * @param string $desc
     * @param bool   $required
     * @param mixed  $default
     */
    public function addOpt(string $name, string $shorts, string $desc, bool $required = false, $default = null): void
    {
        $opt = Option::new($name, $desc, $required, $default);
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
     * @return bool
     */
    public function hasDefined(string $name): bool
    {
        return isset($this->defined[$name]);
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
    public function getMatchedOptions(): array
    {
        return $this->matched;
    }
}
