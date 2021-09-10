<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use RuntimeException;
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

/**
 * Class Flags
 *
 * @package Toolkit\PFlag
 */
class Flags extends AbstractFlags
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
     * @return self
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
     * @param array $flags
     *
     * @return bool
     */
    public function doParse(array $flags): bool
    {
        // $parsing = true;

        // $status = self::STATUS_OK;
        while (true) {
            // if (!$parsing) {
            //     $this->rawArgs[] = $this->flags;
            //     continue;
            // }

            [$parsing, $status] = $this->parseOneOption();
            if ($parsing) {
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

            // $status = self::STATUS_ARG is arg, collect value.
            // $this->rawArgs[] = array_shift($this->flags);
        }

        // revert flags.
        $this->flags = $flags;

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
        if ($this->isAutoBindArgs()) {
            $this->bindingArguments();
        }

        return true;
    }

    /**
     * parse one flag.
     *
     * default, will stop on:
     * - `autoRenderHelp=true` AND found `-h|--help` flag
     * - `stopOnFistArg=true` AND found first arg(not an option)
     *
     * @return array [goon: bool, status: int]
     */
    protected function parseOneOption(): array
    {
        if (!$this->flags) {
            return [false, self::STATUS_OK];
        }

        $val = $this->flags[0];

        // NOTICE: will stop parse option on found '--'
        if ($val === '--') {
            array_shift($this->flags);
            $this->appendRawArgs($this->flags);
            return [false, self::STATUS_OK];
        }

        // check is an option name.
        $name = $this->filterOptionName($val);
        if ('' === $name) {
            $goon   = true;
            $status = self::STATUS_ARG;

            // stop on found first arg.
            if ($this->stopOnFistArg) {
                $goon   = false;
                $status = self::STATUS_OK;

                $this->appendRawArgs($this->flags);
            } else { // collect arg
                $this->rawArgs[] = array_shift($this->flags);
            }

            return [$goon, $status];
        }

        // remove first: $val
        array_shift($this->flags);

        // enable auto render help
        if ($this->autoRenderHelp && ($val === '-h' || $val === '--help')) {
            return [false, self::STATUS_HELP];
        }

        // short or long
        $isShort = $val[1] !== '-';
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
            if ($this->skipOnUndefined) {
                $this->rawArgs[] = $val;
                return [true, self::STATUS_OK];
            }

            throw new FlagException("flag option provided but not defined: $val", 404);
        }

        $opt = $this->options[$rName];

        // bool option default always set TRUE.
        if ($opt->isBoolean()) {
            // only allow set bool value by --opt=false
            $boolVal = !$hasVal || Str::toBool($value);
            $opt->setValue($boolVal);
        } else {
            // need value - check next is an value.
            if (!$hasVal && isset($this->flags[0])) {
                $hasVal = true;
                // value is next element
                $ntArg = $this->flags[0];

                // is not an option value.
                if ($ntArg[0] === '-') {
                    $hasVal = false;
                } else {
                    $value = $ntArg;
                    array_shift($this->flags);
                }
            }

            if (!$hasVal) {
                throw new FlagException("flag option '$val' needs an value", 400);
            }

            // set value
            $opt->setValue($value);
        }

        $this->addMatched($opt);
        return [true, self::STATUS_OK];
    }

    /**
     * @param array $args
     */
    private function appendRawArgs(array $args): void
    {
        foreach ($args as $arg) {
            $this->rawArgs[] = $arg;
        }
    }

    /**
     * check and get option Name
     *
     * invalid:
     * - empty string
     * - no prefix '-' (is argument)
     * - invalid option name as argument. eg: '- '
     *
     * @param string $val
     *
     * @return string
     */
    private function filterOptionName(string $val): string
    {
        // is not an option.
        if ('' === $val || $val[0] !== '-') {
            return '';
        }

        return ltrim($val, '-');
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
        $this->resetResults();
    }

    public function resetDefine(): void
    {
        $this->options = [];
        $this->resetArguments();
    }

    public function resetResults(): void
    {
        parent::resetResults();

        $this->matched = [];
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
        if (!$this->parsed) {
            throw new RuntimeException('must be call "bindingArguments()" after option parsed');
        }

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

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'flags'   => $this->flags,
            'rawArgs' => $this->rawArgs,
            'opts'    => $this->getOpts(),
            'args'    => $this->getArgs(),
        ];
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
     * @param array $argRules
     */
    public function setArgRules(array $argRules): void
    {
        $this->addArgsByRules($argRules);
    }

    /**
     * Add and argument by rule
     *
     * @param string       $name
     * @param string|array $rule
     * @see argRules for an rule
     *
     * @return self
     */
    public function addArgByRule(string $name, $rule): AbstractFlags
    {
        parent::addArgByRule($name, $rule);

        $index  = count($this->arguments);
        $define = $this->parseRule($rule, $name, $index, false);
        /** @var Argument $arg */
        $arg = Argument::newByArray($name, $define);

        return $this->addArgument($arg);
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
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasArg($nameOrIndex): bool
    {
        if (is_string($nameOrIndex)) {
            if (!isset($this->name2index[$nameOrIndex])) {
                return false;
            }

            $index = $this->name2index[$nameOrIndex];
        } else {
            $index = (int)$nameOrIndex;
        }

        return isset($this->arguments[$index]);
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
    public function getArgs(): array
    {
        $args = [];
        foreach ($this->arguments as $argument) {
            $args[] = $argument->getValue();
        }

        return $args;
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
     * @param array $optRules
     */
    public function setOptRules(array $optRules): void
    {
        $this->addOptsByRules($optRules);
    }

    /**
     * Add and option by rule
     *
     * @param string       $name
     * @param string|array $rule
     * @see optRules for rule
     *
     * @return self
     */
    public function addOptByRule(string $name, $rule): AbstractFlags
    {
        parent::addOptByRule($name, $rule);

        $define = $this->parseRule($rule, $name);
        /** @var Option $option */
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
     * Has matched option
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasOpt(string $name): bool
    {
        return isset($this->matched[$name]);
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
     * @param string $name
     *
     * @return Option|null
     */
    public function getOption(string $name): ?Option
    {
        return $this->matched[$name] ?? null;
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
     * @return array
     */
    public function getOptSimpleDefines(): array
    {
        $map = [];
        foreach ($this->options as $name => $define) {
            $names   = $define['shorts'];
            $names[] = $name;

            $helpName = FlagUtil::buildOptHelpName($names);

            $map[$helpName] = $define['desc'];
        }

        return $map;
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
}
