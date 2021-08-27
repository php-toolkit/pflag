<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use Toolkit\Cli\Cli;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\Stdlib\Str;
use function array_shift;
use function array_slice;
use function count;
use function is_array;
use function is_string;
use function ltrim;
use function str_split;
use function strlen;
use function substr;
use function vdump;

/**
 * Class Flags
 *
 * @package Toolkit\PFlag
 */
class Flags extends AbstractParser
{
    /**
     * @var self
     */
    private static $std;

    // ------------------- opts -------------------

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
    private $options = [];

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

    // ------------------- args -------------------
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
     * @var bool
     */
    private $autoBindArgs = true;

    /**
     * @return $this
     */
    public static function std(): self
    {
        if (!self::$std) {
            self::$std = new self();
        }

        return self::$std;
    }

    /**************************************************************************
     * parse command option flags
     **************************************************************************/

    /**
     * @param array|null $flags
     */
    public function parse(array $flags = null): bool
    {
        if ($flags === null) {
            $flags = $_SERVER['argv'];
            $sFile = array_shift($flags);
            $this->setScriptFile($sFile);
        }

        $this->parsed  = true;
        $this->rawArgs = $this->flags = $flags;

        while (true) {
            [$goon, $status] = $this->parseOne();
            if ($goon) {
                continue;
            }

            // parse end.
            if (self::STATUS_OK === $status) {
                break;
            }

            // echo error and display help
            if (self::STATUS_ERR === $status) {
                Cli::colored('ERROR: TODO flag error', 'error');
                $this->displayHelp();
                break;
            }

            // display help
            if (self::STATUS_HELP === $status) {
                $this->displayHelp();
                break;
            }
        }

        $this->parseStatus = $status;
        if ($status !== self::STATUS_OK) {
            return false;
        }

        // check required opts
        if ($this->requiredOpts) {
            foreach ($this->requiredOpts as $name) {
                if (!isset($this->matched[$name])) {
                    throw new FlagException("flag option '$name' is required");
                }
            }
        }

        // binding remaining args.
        if ($this->autoBindArgs) {
            $this->bindingArguments();
        }

        return true;
    }

    /**
     * parse one flag.
     *
     * will stop on:
     * - found `-h|--help` flag
     * - found first arg(not an option)
     *
     * @return array [goon: bool, status: int]
     */
    protected function parseOne(): array
    {
        if (!$args = $this->rawArgs) {
            return [false, self::STATUS_OK];
        }

        $arg = array_shift($this->rawArgs);

        // NOTICE: will stop parse option on found '--'
        if ($arg === '--') {
            return [false, self::STATUS_OK];
        }

        // show help
        if ($arg === '-h' || $arg === '--help') {
            return [false, self::STATUS_HELP];
        }

        // is not an option.
        if ('' === $arg || $arg[0] !== '-') {
            $this->rawArgs = $args; // revert args on return
            return [false, self::STATUS_OK];
        }

        $name = ltrim($arg, '-');

        // invalid option name as argument. eg: '- '
        if ('' === $name) {
            $this->rawArgs = $args; // revert args on return
            return [false, self::STATUS_OK];
        }

        // short or long
        $isShort = $arg[1] !== '-';
        $optLen  = strlen($name);

        // If is merged short opts. eg: -abc
        if ($isShort && $optLen > 1) {
            $this->parseMergedShorts($name);
            return [true, self::STATUS_OK];
        }

        $value  = '';
        $hasVal = false;
        for ($i = 0; $i < $optLen; $i++) {
            if ($name[$i] === '=') {
                $hasVal = true;
                $name   = substr($name, 0, $i);

                // fix: `--name=` no value string.
                if ($i + 1 < $optLen) {
                    $value = substr($name, $i + 1);
                }
            }
        }

        $rName = $this->resolveAlias($name);
        if (!isset($this->options[$rName])) {
            throw new FlagException("flag option provided but not defined: $arg", 404);
        }

        $opt = $this->options[$rName];

        // bool option default always set TRUE.
        if ($opt->isBoolean()) {
            // only allow set bool value by --opt=false
            $boolVal = !$hasVal || Str::toBool($value);

            $opt->setValue($boolVal);
        } else {
            if (!$hasVal && count($this->rawArgs) > 0) {
                $hasVal = true;
                // value is next element
                $ntArg = $this->rawArgs[0];

                // is not an option value.
                if ($ntArg[0] === '-') {
                    $hasVal = false;
                } else {
                    $value = array_shift($this->rawArgs);
                }
            }

            if (!$hasVal) {
                vdump($opt);
                throw new FlagException("flag option '$arg' needs an value", 400);
            }

            // set value
            $opt->setValue($value);
        }

        $this->addMatched($opt);
        return [true, self::STATUS_OK];
    }

    /**
     * @param string $shorts eg: 'abc' from '-abc'
     */
    protected function parseMergedShorts(string $shorts): bool
    {
        // posix: '-abc' will expand to '-a=bc'
        if ($this->shortStyle === self::SHORT_STYLE_POSIX) {
            $option = $this->resolveAlias($shorts[0]);
            $this->setOptValue($option, substr($shorts, 1));
            return true;
        }

        // gnu: '-abc' will expand to '-a -b -c'
        foreach (str_split($shorts) as $short) {
            $option = $this->resolveAlias($short);
            $this->setOptValue($option, true);
        }
        return true;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return bool
     */
    protected function setOptValue(string $name, $value): bool
    {
        $name = $this->resolveAlias($name);
        if (!isset($this->options[$name])) {
            throw new FlagException("flag option provided but not defined: $name", 404);
        }

        $this->options[$name]->setValue($value);
        return true;
    }

    /**
     * @param bool $clearDefined
     */
    public function reset(bool $clearDefined = false): void
    {
        if ($clearDefined) {
            $this->options = [];
            $this->resetArguments();
        }

        // clear match results
        $this->parsed  = false;
        $this->matched = [];
        $this->rawArgs = $this->flags = [];
    }

    /**************************************************************************
     * parse and binding command arguments
     **************************************************************************/

    /**
     * Parse and binding command arguments
     *
     * NOTICE: must call it on options parsed.
     */
    public function bindingArguments(): self
    {
        // parse arguments
        $args = $this->parseRawArgs($this->rawArgs);

        // collect argument values
        foreach ($this->arguments as $index => $arg) {
            if (!isset($args[$index]) && $arg->isRequired()) {
                $mark = $arg->getNameMark();
                throw new FlagException("flag argument $mark is required");
            }

            if ($arg->isArray()) {
                // remain args
                $values = array_slice($args, $index);

                foreach ($values as $value) {
                    $arg->setValue($value);
                }
            } else {
                $arg->setValue($args[$index]);
            }
        }

        return $this;
    }

    /**
     * @param bool $withColor
     *
     * @return string
     */
    public function buildHelp(bool $withColor = true): string
    {
        return $this->doBuildHelp($this->arguments, $this->options, $withColor);
    }

    /**************************************************************************
     * arguments
     **************************************************************************/

    /**
     * @param string     $name
     * @param string     $desc
     * @param string     $type The argument data type. default is: string. {@see FlagType}
     * @param bool       $required
     * @param null|mixed $default
     */
    public function addArg(
        string $name,
        string $desc,
        string $type = '',
        bool $required = false,
        $default = null
    ): void {
        /** @var Argument $arg */
        $arg = Argument::new($name, $desc, $type, $required, $default);

        $this->addArgument($arg);
    }

    /**
     * @param array $rules
     *
     * @see addArgByRule()
     */
    public function addArgsByRules(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            $this->addArgByRule($name, $rule);
        }
    }

    /**
     * Add and argument by rule
     *
     * rule:
     *   - string is rule string. (format: 'type;required;default;desc')
     *   - array is define item {@see Flags::DEFINE_ITEM}
     *
     * @param string       $name
     * @param string|array $rule
     *
     * @return self
     */
    public function addArgByRule(string $name, $rule): self
    {
        $index  = count($this->arguments);
        $define = $this->parseRule($rule, $name, $index, false);

        return $this->addArgument(Argument::newByArray($name, $define));
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
     * @param Argument $argument
     *
     * @return self
     */
    public function addArgument(Argument $argument): self
    {
        $isArray  = $argument->isArray();
        $required = $argument->isRequired();

        $index = count($this->arguments);
        $argument->setIndex($index);
        $argument->init();

        $name = $argument->getName();
        $mark = $argument->getNameMark();

        if ($required && $argument->hasDefault()) {
            throw new FlagException("cannot set a default value, if argument $mark is required. ");
        }

        // NOTICE: only allow one array argument and must be at last.
        if ($this->arrayArg && $isArray) {
            throw new FlagException("cannot add argument $mark after an array argument");
        }

        if ($this->optionalArg && $required) {
            throw new FlagException("cannot add a required argument $mark after an optional one");
        }

        $this->arrayArg    = $this->arrayArg || $isArray;
        $this->optionalArg = $this->optionalArg || !$required;

        // append
        $this->arguments[] = $argument;
        // record index
        $this->name2index[$name] = $index;
        return $this;
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
     * @return array
     */
    public function getArgs(): array
    {
        $args = [];
        foreach ($this->arguments as $argument) {
            $args[] = $argument->getValue();
        }

        return $args;
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

    /**************************************************************************
     * options
     **************************************************************************/

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
     * @param array $rules
     */
    public function addOptsByRules(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            $this->addOptByRule($name, $rule);
        }
    }

    /**
     * Add and option by rule
     *
     * rule:
     *   - string is rule string. (format: 'type;required;default;desc').
     *   - array is define item {@see Flags::DEFINE_ITEM}
     *
     * @param string       $name
     * @param string|array $rule
     *
     * @return self
     */
    public function addOptByRule(string $name, $rule): self
    {
        $define = $this->parseRule($rule, $name);
        $option = Option::newByArray($define['name'], $define);

        if (is_array($rule) && isset($rule['alias'])) {
            $option->setAlias($rule['alias']);
        }

        return $this->addOption($option);
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
     *
     * @return self
     */
    public function addOption(Option $option): self
    {
        $name = $option->getName();

        if (isset($this->options[$name])) {
            throw new FlagException('cannot repeat add option: ' . $name);
        }

        // has alias
        if ($alias = $option->getAlias()) {
            if (isset($this->options[$alias])) {
                throw new FlagException("cannot assign alias '$alias' to option '$name', '$alias' is exists option");
            }

            $this->setAlias($name, $alias, true);
        }

        // has shorts
        if ($ss = $option->getShorts()) {
            foreach ($ss as $s) {
                if (isset($this->options[$s])) {
                    throw new FlagException("cannot assign short '$s' to option '$name', '$s' is exists option");
                }

                $this->setAlias($name, $s, true);
            }
        }

        $option->init();

        if ($required = $option->isRequired()) {
            $this->requiredOpts[] = $name;
        }

        // add to defined
        $this->options[$name] = $option;
        if ($option->hasDefault()) {
            if ($required) {
                throw new FlagException("cannot set a default value, if flag is required. flag: $name");
            }

            $this->matched[$name] = $option;
        }

        return $this;
    }

    /**
     * @param Option $option
     */
    protected function addMatched(Option $option): void
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
        if ($opt = $this->getOption($name)) {
            return $opt->getValue();
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
        return $this->options[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasDefined(string $name): bool
    {
        return isset($this->options[$name]);
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
        return $this->options;
    }

    /**
     * @return Option[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return Option[]
     */
    public function getMatchedOptions(): array
    {
        return $this->matched;
    }

    /**
     * @return bool
     */
    public function isAutoBindArgs(): bool
    {
        return $this->autoBindArgs;
    }

    /**
     * @param bool $autoBindArgs
     */
    public function setAutoBindArgs(bool $autoBindArgs): void
    {
        $this->autoBindArgs = $autoBindArgs;
    }

    /**
     * @return bool
     */
    public function isStopOnUndefined(): bool
    {
        return $this->stopOnUndefined;
    }

    /**
     * @param bool $stopOnUndefined
     */
    public function setStopOnUndefined(bool $stopOnUndefined): void
    {
        $this->stopOnUndefined = $stopOnUndefined;
    }
}
