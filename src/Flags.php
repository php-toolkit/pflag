<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use InvalidArgumentException;
use RuntimeException;
use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Exception\FlagParseException;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\Stdlib\Str;
use function array_shift;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function str_split;
use function strlen;
use function strpos;
use function substr;

/**
 * Class Flags
 *
 * @package Toolkit\PFlag
 */
class Flags extends FlagsParser
{
    /**
     * @var self|null
     */
    private static ?self $std = null;

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
    private array $options = [];

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
    private array $matched = [];

    // ------------------- args -------------------
    /**
     * @var array [name => index]
     */
    private array $name2index = [];

    /**
     * @var Argument[]
     */
    private array $arguments = [];

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

            // display help
            if (self::STATUS_HELP === $status) {
                $this->displayHelp();
                break;
            }
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

        $this->remainArgs = $this->rawArgs;

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
        $name = FlagUtil::filterOptionName($val);
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
        $eqPos  = strpos($name, '=');
        if ($eqPos > 0) {
            $hasVal = true;
            $value  = substr($name, $eqPos + 1);
            $name   = substr($name, 0, $eqPos);
        }

        $rName = $this->resolveAlias($name);
        if (!isset($this->options[$rName])) {
            if ($this->skipOnUndefined) {
                $this->rawArgs[] = $val;
                return [true, self::STATUS_OK];
            }

            throw new FlagParseException("flag option provided but not defined: $val", 404);
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
                throw new FlagParseException("flag option '$val' needs an value", 400);
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
    protected function setOptValue(string $name, mixed $value): bool
    {
        $name = $this->resolveAlias($name);
        if (!isset($this->options[$name])) {
            throw new FlagException("cannot set value for not defined option: $name", 404);
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
        foreach ($this->arguments as $arg) {
            $arg->setTrustedValue(null);
        }
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
        $args = $this->parseRawArgs($remains = $this->rawArgs);

        // collect argument values
        foreach ($this->arguments as $index => $arg) {
            $name = $arg->getName();

            if (isset($args[$name])) {
                $value = $args[$name];
                unset($args[$name]);
            } elseif (isset($args[$index])) {
                $value = $args[$index];
                unset($args[$index]);
            } else {
                if ($arg->isRequired()) {
                    $mark = $arg->getNameMark();
                    throw new FlagException("flag argument $mark is required");
                }
                continue;
            }

            // array: collect all remain args
            if ($arg->isArray()) {
                $arg->setValue($value);
                foreach ($args as $val) {
                    $arg->setValue($val);
                }

                $remains = $args = [];
            } else {
                array_shift($remains);
                $arg->setValue($value);
            }
        }

        if ($remains) {
            $remains = array_values($remains);
            if ($this->strictMatchArgs) {
                throw new FlagException(sprintf('unknown arguments (error: "%s").', implode(' ', $remains)));
            }
        }

        $this->remainArgs = $remains;
        return $this;
    }

    /**
     * @param bool $withColor
     *
     * @return string
     */
    public function buildHelp(bool $withColor = true): string
    {
        return $this->doBuildHelp($this->arguments, $this->options, $withColor, $this->hasShortOpts());
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

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return count($this->options) > 0 || count($this->arguments) > 0;
    }

    /**************************************************************************
     * arguments
     **************************************************************************/

    /**
     * @param string     $name
     * @param string     $desc
     * @param string     $type The argument data type. default is: string. {@see FlagType}
     * @param bool       $required
     * @param mixed|null $default
     * @param array{helpType: string, validator: callable|ValidatorInterface}  $moreInfo
     *
     * @return static
     */
    public function addArg(
        string $name,
        string $desc,
        string $type = '',
        bool $required = false,
        mixed $default = null,
        array $moreInfo = []
    ): static {
        $arg = Argument::new($name, $desc, $type, $required, $default);
        $arg->setHelpType($moreInfo['helpType'] ?? '');
        $arg->setValidator($moreInfo['validator'] ?? null);

        $this->addArgument($arg);
        return $this;
    }

    /**
     * Add and argument by rule
     *
     * @param string       $name
     * @param array|string $rule
     *
     * @return self
     * @see argRules for an rule
     */
    public function addArgByRule(string $name, array|string $rule): static
    {
        $index  = count($this->arguments);
        $define = $this->parseRule($rule, $name, $index, false);

        $arg = Argument::newByArray($name, $define);

        parent::addArgByRule($name, $rule);
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
        if ($this->isLocked()) {
            throw new FlagException('flags has been locked, cannot add argument');
        }

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

        // record index
        if ($name) {
            if (isset($this->name2index[$name])) {
                throw new FlagException('cannot repeat add named argument: ' . $name);
            }

            $this->name2index[$name] = $index;
        }

        // append
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasArg(int|string $nameOrIndex): bool
    {
        if (is_string($nameOrIndex)) {
            if (!isset($this->name2index[$nameOrIndex])) {
                return false;
            }

            $index = $this->name2index[$nameOrIndex];
        } else {
            $index = $nameOrIndex;
        }

        return isset($this->arguments[$index]);
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed $value
     */
    public function setArg(int|string $nameOrIndex, mixed $value): void
    {
        $arg = $this->mustGetArgument($nameOrIndex);
        $arg->setValue($value);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedArg(string $name, mixed $value): void
    {
        $arg = $this->mustGetArgument($name);

        $arg->setTrustedValue($value);
    }

    /**
     * @param int|string $nameOrIndex
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustArg(int|string $nameOrIndex, string $errMsg = ''): mixed
    {
        $arg = $this->mustGetArgument($nameOrIndex);
        if ($arg->hasValue()) {
            return $arg->getValue();
        }

        if (!$errMsg) {
            $errName = $arg->getNameMark();
            $errMsg  = "The argument '$errName' is required";
        }

        throw new InvalidArgumentException($errMsg);
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getArg(int|string $nameOrIndex, mixed $default = null): mixed
    {
        $arg = $this->mustGetArgument($nameOrIndex);

        if ($arg->hasValue()) {
            return $arg->getValue();
        }

        return $default ?? $arg->getTypeDefault();
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return Argument
     */
    protected function mustGetArgument(int|string $nameOrIndex): Argument
    {
        $arg = $this->getArgument($nameOrIndex);
        if (!$arg) { // not exist
            throw new FlagException("flag argument '$nameOrIndex' is undefined");
        }

        return $arg;
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return Argument|null
     */
    public function getArgument(int|string $nameOrIndex): ?Argument
    {
        $index = $this->getArgIndex($nameOrIndex);

        return $this->arguments[$index] ?? null;
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return array
     */
    public function getArgDefine(int|string $nameOrIndex): array
    {
        $index = $this->getArgIndex($nameOrIndex);
        if ($index < 0) {
            throw new FlagException("flag argument '$nameOrIndex' is undefined");
        }

        return $this->arguments[$index]->toArray();
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return int
     */
    public function getArgIndex(int|string $nameOrIndex): int
    {
        if (is_string($nameOrIndex)) {
            return $this->name2index[$nameOrIndex] ?? -1;
        }

        $index = $nameOrIndex;
        return isset($this->arguments[$index]) ? $index : -1;
    }

    /**
     * Whether input argument
     *
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasInputArg(int|string $nameOrIndex): bool
    {
        $arg = $this->getArgument($nameOrIndex);
        if (!$arg) {
            return false;
        }

        return $arg->hasValue();
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

    protected function resetArguments(): void
    {
        $this->name2index = [];
        $this->arguments  = [];
    }

    /**************************************************************************
     * options
     **************************************************************************/

    /**
     * Add option
     *
     * @param string $name
     * @param string $shortcut
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool   $required
     * @param mixed|null $default
     * @param array{aliases: array, helpType: string} $moreInfo
     *
     * @return static
     */
    public function addOpt(
        string $name,
        string $shortcut,
        string $desc,
        string $type = '',
        bool $required = false,
        mixed $default = null,
        array $moreInfo = []
    ): static {
        $opt = Option::new($name, $desc, $type, $required, $default);
        $opt->setAliases($moreInfo['aliases'] ?? []);
        $opt->setShortcut($shortcut);

        $this->addOption($opt);
        return $this;
    }

    /**
     * Add and option by rule
     *
     * @param string       $name
     * @param array|string $rule
     *
     * @return static
     * @see optRules for rule
     */
    public function addOptByRule(string $name, array|string $rule): static
    {
        $define = $this->parseRule($rule, $name);
        $option = Option::newByArray($define['name'], $define);

        if (is_array($rule) && isset($rule['aliases'])) {
            $option->setAliases($rule['aliases']);
        }

        parent::addOptByRule($name, $rule);
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
        if ($this->isLocked()) {
            throw new FlagException('flags has been locked, cannot add argument');
        }

        $name = $option->getName();
        if (isset($this->options[$name])) {
            throw new FlagException('cannot repeat add option: ' . $name);
        }

        // has aliases
        if ($aliases = $option->getAliases()) {
            foreach ($aliases as $alias) {
                if (isset($this->options[$alias])) {
                    throw new FlagException("cannot assign alias '$alias' to option '$name', '$alias' is exists option");
                }

                $this->setAlias($name, $alias, true);
            }
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
        return isset($this->options[$name]);
    }

    /**
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOpt(string $name, mixed $default = null): mixed
    {
        $opt = $this->mustGetOption($name);

        if ($opt->hasValue()) {
            return $opt->getValue();
        }

        return $default ?? $opt->getTypeDefault();
    }

    /**
     * @param string $name
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustOpt(string $name, string $errMsg = ''): mixed
    {
        $opt = $this->mustGetOption($name);

        if ($opt->hasValue()) {
            return $opt->getValue();
        }

        $errMsg = $errMsg ?: "The option '$name' is required";
        throw new InvalidArgumentException($errMsg);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setOpt(string $name, mixed $value): void
    {
        $opt = $this->mustGetOption($name);
        $opt->setValue($value);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedOpt(string $name, mixed $value): void
    {
        $opt = $this->mustGetOption($name);
        $opt->setTrustedValue($value);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getOptDefine(string $name): array
    {
        return $this->mustGetOption($name)->toArray();
    }

    /**
     * @param string $name
     *
     * @return Option
     */
    protected function mustGetOption(string $name): Option
    {
        $opt = $this->getOption($name);
        if (!$opt) { // not exist option
            throw new FlagException("flag option '$name' is undefined");
        }

        return $opt;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasInputOpt(string $name): bool
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
    public function getArgsHelpLines(): array
    {
        $helpData = [];
        foreach ($this->arguments as $arg) {
            $name = $arg->getHelpName();
            // append
            $helpData[$name] = $arg->getDesc(true);
        }

        return $helpData;
    }

    /**
     * @return array
     */
    public function getOptsHelpLines(): array
    {
        $helpData = [];
        foreach ($this->options as $name => $opt) {
            if ($this->showHiddenOpt === false && $opt['hidden']) {
                continue;
            }

            [$helpName, $fmtDesc] = $this->buildOptHelpLine($name, $opt->toArray());
            $helpData[$helpName]  = $fmtDesc;
        }

        ksort($helpData);
        return $helpData;
    }

    /**
     * @param string $name
     *
     * @return Option|null
     */
    public function getOption(string $name): ?Option
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Alias of the getOption();
     *
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

    /**
     * @param string $name
     *
     * @return Option|null
     */
    public function getMatchedOption(string $name): ?Option
    {
        return $this->matched[$name] ?? null;
    }
}
