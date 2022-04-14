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
use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Exception\FlagParseException;
use Toolkit\Stdlib\OS;
use Toolkit\Stdlib\Str;
use function array_shift;
use function array_values;
use function count;
use function current;
use function explode;
use function implode;
use function is_callable;
use function is_string;
use function ksort;
use function next;
use function sprintf;
use function str_split;
use function strlen;
use function substr;

/**
 * Class SFlags
 *
 * @package Toolkit\PFlag
 */
class SFlags extends FlagsParser
{
    /**
     * @var self|null
     */
    private static ?self $std = null;

    // ------------------------ opts ------------------------

    /**
     * The options definitions
     * - item please {@see DEFINE_ITEM}
     *
     * ```php
     * [
     *  'name' => self::DEFINE_ITEM,
     * ]
     * ```
     *
     * @var array
     */
    private array $optDefines = [];

    /**
     * Parsed option values
     *
     * ```php
     * [name => value]
     * ```
     *
     * @var array
     */
    private array $opts = [];

    // ------------------------ args ------------------------

    /**
     * The arguments definitions
     *
     * - item please {@see DEFINE_ITEM}
     *
     * ```php
     * [
     *  self::DEFINE_ITEM,
     * ]
     * ```
     *
     * @var array[]
     */
    private array $argDefines = [];

    /**
     * The mapping argument name to index
     *
     * @var array
     */
    private array $name2index = [];

    /**
     * Parsed input argument values
     * - key is a self-increasing index
     *
     * ```php
     * [
     *  'arg0',
     *  'arg1',
     *  'arg2',
     * ]
     * ```
     *
     * @var array
     */
    private array $args = [];

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

    /**
     * @param bool $withColor
     *
     * @return string
     */
    public function buildHelp(bool $withColor = true): string
    {
        return $this->doBuildHelp($this->argDefines, $this->optDefines, $withColor, $this->hasShortOpts());
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return count($this->optDefines) > 0 || count($this->argDefines) > 0;
    }

    /****************************************************************
     * add opt&arg
     ***************************************************************/

    /**
     * @param string $name
     * @param string $shortcut
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool $required
     * @param mixed|null $default
     * @param array{aliases: array<string>, helpType: string} $moreInfo
     *
     * @return SFlags
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
        $define = self::DEFINE_ITEM;

        $define['name'] = $name;
        $define['desc'] = $desc;
        $define['type'] = $type ?: FlagType::STRING;

        $define['required'] = $required;
        $define['default']  = $default;
        $define['shorts']   = $shortcut ? Str::explode($shortcut, ',') : [];

        if (isset($moreInfo['helpType'])) {
            $define['helpType'] = $moreInfo['helpType'];
        }
        if (isset($moreInfo['aliases'])) {
            $define['aliases'] = $moreInfo['aliases'];
        }

        $this->addOptDefine($define);
        return $this;
    }

    /**
     * @param string $name
     * @param string $desc
     * @param string $type The argument data type. default is: string. {@see FlagType}
     * @param bool $required
     * @param mixed|null $default
     * @param array{helpType: string, validator: callable|ValidatorInterface}  $moreInfo
     *
     * @return SFlags
     */
    public function addArg(
        string $name,
        string $desc,
        string $type = '',
        bool $required = false,
        mixed $default = null,
        array $moreInfo = []
    ): static {
        $define = self::DEFINE_ITEM;

        $define['name']  = $name;
        $define['desc']  = $desc;
        $define['index'] = count($this->argDefines);
        $define['type']  = $type ?: FlagType::STRING;

        $define['required'] = $required;
        $define['default']  = $default;

        if (isset($moreInfo['helpType'])) {
            $define['helpType'] = $moreInfo['helpType'];
        }

        if (isset($moreInfo['validator'])) {
            $define['validator'] = $moreInfo['validator'];
        }

        $this->addArgDefine($define);
        return $this;
    }

    /**
     * @param string $name
     * @param array|string $rule
     *
     * @return static
     */
    public function addArgByRule(string $name, array|string $rule): static
    {
        // parse rule
        $index  = count($this->argDefines);
        $define = $this->parseRule($rule, $name, $index, false);

        // add define
        $this->addArgDefine($define);

        parent::addArgByRule($name, $rule);
        return $this;
    }

    /**
     * @param string $name
     * @param array|string $rule
     *
     * @return static
     */
    public function addOptByRule(string $name, array|string $rule): static
    {
        // parse rule
        $define = $this->parseRule($rule, $name);

        // add define
        $this->addOptDefine($define);

        parent::addOptByRule($name, $rule);
        return $this;
    }

    /****************************************************************
     * parse options and arguments
     ***************************************************************/

    /**
     * Parse options by pre-defined rules
     *
     * Usage:
     *
     * ```php
     * $rawFlags = $_SERVER['argv'];
     * // NOTICE: must shift first element.
     * $scriptFile = array_shift($rawFlags);
     *
     * $optRules = [];
     * $argRules = [];
     *
     * $fs = SFlags::new()->parseDefined($rawFlags, $optRules, $argRules);
     *
     * // get value
     * $rawArgs = $this->getRawArgs();
     * ```
     *
     * @param array $rawFlags
     * @param array $optRules The want parsed options rules defines {@see $optRules}
     * @param array $argRules The arg rules {@see $argRules}. if not empty, will parse arguments after option parsed
     *
     * @return bool
     */
    public function parseDefined(array $rawFlags, array $optRules, array $argRules = []): bool
    {
        if ($this->isParsed()) {
            return true;
        }

        $this->setArgRules($argRules);
        $this->setOptRules($optRules);

        return $this->parse($rawFlags);
    }

    /**
     * Parse options by pre-defined
     *
     * Usage:
     *
     * ```php
     * $rawFlags = $_SERVER['argv'];
     * // NOTICE: must shift first element.
     * $scriptFile = array_shift($rawFlags);
     *
     * $fs = SFlags::new();
     * $fs->setArgRules($argRules);
     * $fs->setOptRules($optRules);
     *
     * $rawArgs = $fs->parse($rawFlags);
     * ```
     *
     * Supports options style:
     *
     * ```bash
     * -s
     * -s <value>
     * -s=<value>
     * --long-opt
     * --long-opt <value>
     * --long-opt=<value>
     * ```
     *
     * Supports args style:
     *
     * ```bash
     * <value>
     * arg=<value>
     * ```
     *
     * @link http://php.net/manual/zh/function.getopt.php#83414
     *
     * @param array $flags
     *
     * @return bool
     */
    public function doParse(array $flags): bool
    {
        // parse rules
        // $this->parseOptRules($this->optRules);
        // $this->parseArgRules($this->argRules);

        $optParseEnd = false;
        $parseStatus = self::STATUS_OK;
        while (false !== ($p = current($flags))) {
            next($flags);

            // option parse end, collect remaining arguments.
            if ($optParseEnd) {
                $this->rawArgs[] = $p;
                continue;
            }

            // is valid option name
            $optName = FlagUtil::filterOptionName($p);
            if ('' !== $optName) {
                $value  = true; // bool option value default is true.
                $hasVal = false;

                // enable auto render help
                if ($this->autoRenderHelp && ($p === '-h' || $p === '--help')) {
                    $this->displayHelp();
                    $parseStatus = self::STATUS_HELP;
                    break;
                }

                $isShort = true;
                $option  = substr($p, 1);
                // long-opt: (--<opt>)
                if (str_starts_with($option, '-')) {
                    $isShort = false;
                    $option  = substr($option, 1);

                    // long-opt: value specified inline (--<opt>=<value>)
                    if (str_contains($option, '=')) {
                        [$option, $value] = explode('=', $option, 2);
                        $hasVal = $value !== '';
                    }

                    // short-opt: value specified inline (-<opt>=<value>)
                } elseif (isset($option[1]) && $option[1] === '=') {
                    [$option, $value] = explode('=', $option, 2);
                    $hasVal = $value !== '';
                }

                // If is special short opts. eg: -abc
                if ($isShort && strlen($option) > 1) {
                    $this->parseSpecialShorts($option);
                    continue;
                }

                // resolve alias
                $option = $this->resolveAlias($option);
                if (!isset($this->optDefines[$option])) {
                    if ($this->skipOnUndefined) {
                        $this->rawArgs[] = $p;
                        continue;
                    }

                    throw new FlagParseException("flag option provided but not defined: $p", 404);
                }

                $define = $this->optDefines[$option];

                // only allow set bool value by inline. eg: -o=false
                $isBool = $define['type'] === FlagType::BOOL;
                if ($hasVal && $isBool) {
                    $this->setRealOptValue($option, $value, $define);
                    continue;
                }

                // check if next element is a descriptor or a value
                $next = current($flags);
                if ($hasVal === false && $isBool === false) {
                    if (false === FlagUtil::isOptionValue($next)) {
                        throw new FlagParseException("must provide value for the option: $p", 404);
                    }

                    $value = $next;
                    next($flags); // move key pointer
                }

                $this->setRealOptValue($option, $value, $define);
                continue;
            }

            // stop parse options:
            // - on found fist argument.
            // - found '--' will stop parse options
            $isTwoHl = $p === '--';
            if ($this->stopOnFistArg || $isTwoHl) {
                $optParseEnd = true;
                if ($isTwoHl) {
                    continue;
                }
            }

            // collect remaining arguments.
            $this->rawArgs[] = $p;
        }

        $this->parseStatus = $parseStatus;
        if ($parseStatus !== self::STATUS_OK) {
            return false;
        }

        // check required opts
        if ($this->requiredOpts) {
            foreach ($this->requiredOpts as $name) {
                if (!isset($this->opts[$name])) {
                    throw new FlagException("flag option '$name' is required");
                }
            }
        }

        $this->remainArgs = $this->rawArgs;

        // parse defined arguments
        if ($this->isAutoBindArgs()) {
            $this->bindingArguments();
        }
        return true;
    }

    /**
     * @param string $shorts
     */
    private function parseSpecialShorts(string $shorts): void
    {
        // posix: '-abc' will expand to '-a=bc'
        if ($this->shortStyle === self::SHORT_STYLE_POSIX) {
            $option = $this->resolveAlias($shorts[0]);
            $this->setOptValue($option, substr($shorts, 1));
            return;
        }

        // gnu: '-abc' will expand to '-a -b -c'
        foreach (str_split($shorts) as $short) {
            $option = $this->resolveAlias($short);
            $this->setOptValue($option, true);
        }
    }

    /**
     * @param string $option
     * @param mixed $value
     */
    protected function setOptValue(string $option, mixed $value): void
    {
        $option = $this->resolveAlias($option);
        if (!isset($this->optDefines[$option])) {
            throw new FlagException("cannot set value for not defined option: $option", 404);
        }

        $define = $this->optDefines[$option];
        $this->setRealOptValue($option, $value, $define);
    }

    /**
     * @param string $name The option name
     * @param mixed $value
     * @param array $define {@see DEFINE_ITEM}
     */
    protected function setRealOptValue(string $name, mixed $value, array $define): void
    {
        $type  = $define['type'];
        $value = FlagType::fmtBasicTypeValue($type, $value);

        // has validator
        if ($cb = $define['validator']) {
            $ok = $cb($value, $name);
            if ($ok === false) {
                throw new FlagException("flag option '$name' value not pass validate");
            }
        }

        if (FlagType::isArray($type)) {
            $this->opts[$name][] = $value;
        } else {
            $this->opts[$name] = $value;
        }
    }

    /**
     * parse remaining rawArgs as arguments
     *
     * Supports args style:
     *
     * ```bash
     * <value>
     * arg=<value>
     * ```
     */
    public function bindingArguments(): void
    {
        // parse arguments
        $args = $this->parseRawArgs($remains = $this->rawArgs);

        // check and collect argument values
        foreach ($this->argDefines as $index => $define) {
            $name = $define['name'];
            $mark = $name ? "#$index($name)" : "#$index";

            $required = $define['required'];
            $isArray  = FlagType::isArray($define['type']);

            if (isset($args[$name])) {
                $value = $args[$name];
                unset($args[$name]);
            } elseif (isset($args[$index])) {
                $value = $args[$index];
                unset($args[$index]);
            } else {
                if ($required) {
                    throw new FlagException("flag argument $mark is required");
                }
                continue;
            }

            // array: collect all remain args
            if ($isArray) {
                $this->collectArgValue($value, $index, true, $define);

                foreach ($args as $arrValue) {
                    $this->collectArgValue($arrValue, $index, true, $define);
                }
                $remains = $args = [];
            } else {
                array_shift($remains);
                $this->collectArgValue($value, $index, false, $define);
            }
        }

        if ($remains) {
            $remains = array_values($remains);
            if ($this->strictMatchArgs) {
                throw new FlagException(sprintf('unknown arguments (error: "%s").', implode(', ', $remains)));
            }
        }

        $this->remainArgs = $remains;
    }

    /**
     * @param mixed $value
     * @param int $index
     * @param bool $isArray
     * @param array $define
     */
    protected function collectArgValue(mixed $value, int $index, bool $isArray, array $define): void
    {
        $value = FlagType::fmtBasicTypeValue($define['type'], $value);

        // has validator
        if ($cb = $define['validator']) {
            $name = $define['name'] ?: "#$index";

            $ok = $cb($value, $define['name'] ?: "#$index");
            if ($ok === false) {
                throw new FlagException("flag argument '$name' value not pass validate");
            }
        }

        if ($isArray) {
            $this->args[$index][] = $value;
        } else {
            $this->args[$index] = $value;
        }
    }

    /**
     * @param bool $resetDefines
     */
    public function reset(bool $resetDefines = true): void
    {
        if ($resetDefines) {
            $this->resetDefine();
        }

        // clear match results
        $this->resetResults();
    }

    public function resetDefine(): void
    {
        $this->optRules = $this->optDefines = [];
        $this->argRules = $this->argDefines = [];
    }

    public function resetResults(): void
    {
        parent::resetResults();

        $this->opts = $this->args = [];
    }

    /**
     * @param array $define
     * @param string $mark
     * @param string $kind
     */
    protected function checkDefine(array $define, string $mark, string $kind = self::KIND_OPT): void
    {
        $type = $define['type'];
        $name = $define['name'];

        if ($this->isLocked()) {
            throw new FlagException("flags has been locked, cannot add $kind($name)");
        }

        if (isset($this->optDefines[$name])) {
            throw new FlagException("cannot repeat add $kind: $mark");
        }

        // check type
        if (!FlagType::isValid($type)) {
            throw new FlagException("invalid flag type '$type', $kind: $mark");
        }

        // check name.
        if ((self::KIND_OPT === $kind || $name) && !FlagUtil::isValidName($name)) {
            throw new FlagException("invalid flag $kind name: $mark");
        }

        // validator must be callable
        if (!empty($item['validator']) && !is_callable($item['validator'])) {
            throw new InvalidArgumentException("validator must be callable. $kind: $mark");
        }
    }

    /**
     * @param array $define
     */
    protected function addOptDefine(array $define): void
    {
        $type = $define['type'];
        $name = $define['name'];

        $this->checkDefine($define, $name);

        // has default value
        if (isset($define['default'])) {
            if ($define['required']) {
                throw new FlagException("cannot set a default value on flag is required. option: $name");
            }

            $default = FlagType::fmtBasicTypeValue($type, $define['default']);

            // save as value.
            $this->opts[$name] = $define['default'] = $default;
        }

        if ($define['required']) {
            $this->requiredOpts[] = $name;
        }

        // support read value from ENV var
        if ($define['envVar'] && ($envVal = OS::getEnvVal($define['envVar']))) {
            $this->opts[$name] = FlagType::fmtBasicTypeValue($type, $envVal);
        }

        // has aliases
        if ($define['aliases']) {
            foreach ($define['aliases'] as $alias) {
                $this->setAlias($name, $alias, true);
            }
        }

        // has shorts
        if ($define['shorts']) {
            foreach ($define['shorts'] as $short) {
                $this->setAlias($name, $short, true);
            }
        }

        // save parse definition
        $this->optDefines[$name] = $define;
    }

    /**
     * @param array $define
     */
    protected function addArgDefine(array $define): void
    {
        $index = $define['index'];
        $name  = $define['name'];
        $mark  = $name ? "#$index($name)" : "#$index";

        $this->checkDefine($define, $mark, self::KIND_ARG);

        // has name
        if ($name) {
            if (isset($this->name2index[$name])) {
                throw new FlagException('cannot repeat add named argument: ' . $name);
            }

            // set argument name
            $this->name2index[$name] = $index;
        }

        $type = $define['type'];

        // has default value
        $required = $define['required'];
        if (isset($define['default'])) {
            if ($required) {
                throw new FlagException("cannot set a default value on flag is required. argument: $mark");
            }

            $default = FlagType::fmtBasicTypeValue($type, $define['default']);

            // save as value
            $this->args[$index] = $define['default'] = $default;
        }

        // NOTICE: only allow one array argument and must be at last.
        $isArray = FlagType::isArray($define['type']);
        if ($this->arrayArg && $isArray) {
            throw new FlagException("cannot add argument $mark after an array argument");
        }

        if ($this->optionalArg && $required) {
            throw new FlagException("cannot add a required argument $mark after an optional one");
        }

        $this->arrayArg    = $this->arrayArg || $isArray;
        $this->optionalArg = $this->optionalArg || !$required;

        // save define
        $this->argDefines[] = $define;
    }

    /****************************************************************
     * get option and argument value
     ***************************************************************/

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOpt(string $name): bool
    {
        return isset($this->optDefines[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return $this->hasOpt($name);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->getOpt($name, $default);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOpt(string $name, mixed $default = null): mixed
    {
        if (isset($this->opts[$name])) {
            return $this->opts[$name];
        }

        $define = $this->getOptDefine($name);

        return $default ?? FlagType::getDefault($define['type']);
    }

    /**
     * @param string $name
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustOpt(string $name, string $errMsg = ''): mixed
    {
        if (isset($this->opts[$name])) {
            return $this->opts[$name];
        }

        $this->getOptDefine($name);
        $errMsg = $errMsg ?: "The option '$name' is required";
        throw new InvalidArgumentException($errMsg);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getOptDefine(string $name): array
    {
        $define = $this->optDefines[$name] ?? [];
        if (!$define) { // not exist
            throw new FlagException("flag option '$name' is undefined");
        }

        return $define;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setOpt(string $name, mixed $value): void
    {
        $define = $this->optDefines[$name] ?? [];
        if (!$define) { // not exist option
            throw new FlagException("flag option '$name' is undefined");
        }

        $this->opts[$name] = FlagType::fmtBasicTypeValue($define['type'], $value);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedOpt(string $name, mixed $value): void
    {
        $define = $this->optDefines[$name] ?? [];
        if (!$define) { // not exist
            throw new FlagException("flag option '$name' is undefined");
        }

        $this->opts[$name] = $value;
    }

    /**
     * @return array
     */
    public function getOpts(): array
    {
        return $this->opts;
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasArg(int|string $nameOrIndex): bool
    {
        $index = $this->getArgIndex($nameOrIndex);

        return $index > -1;
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getArgument(int|string $nameOrIndex, mixed $default = null): mixed
    {
        return $this->getArg($nameOrIndex, $default);
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed $value
     */
    public function setArg(int|string $nameOrIndex, mixed $value): void
    {
        $index  = $this->mustGetArgIndex($nameOrIndex);
        $define = $this->argDefines[$index];

        // set value
        $this->args[$index] = FlagType::fmtBasicTypeValue($define['type'], $value);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setTrustedArg(string $name, mixed $value): void
    {
        $index = $this->mustGetArgIndex($name);

        $this->args[$index] = $value;
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getArg(int|string $nameOrIndex, mixed $default = null): mixed
    {
        $index = $this->mustGetArgIndex($nameOrIndex);

        if (isset($this->args[$index])) {
            return $this->args[$index];
        }

        // get default with type format
        $define = $this->argDefines[$index];
        return $default ?? FlagType::getDefault($define['type']);
    }

    /**
     * @param int|string $nameOrIndex
     * @param string $errMsg
     *
     * @return mixed
     */
    public function getMustArg(int|string $nameOrIndex, string $errMsg = ''): mixed
    {
        $index = $this->mustGetArgIndex($nameOrIndex);
        if (isset($this->args[$index])) {
            return $this->args[$index];
        }

        if (!$errMsg) {
            $define  = $this->argDefines[$index];
            $errName = $define['name'] ? "#$index({$define['name']})" : "#$index";
            $errMsg  = "The argument '$errName' is required";
        }

        throw new InvalidArgumentException($errMsg);
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return int
     */
    protected function mustGetArgIndex(int|string $nameOrIndex): int
    {
        $index = $this->getArgIndex($nameOrIndex);
        if ($index < 0) {
            throw new FlagException("flag argument '$nameOrIndex' is undefined");
        }

        return $index;
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return int Will return -1 if arg not exists
     */
    public function getArgIndex(int|string $nameOrIndex): int
    {
        if (!is_string($nameOrIndex)) {
            $index = $nameOrIndex;
            return isset($this->argDefines[$index]) ? $index : -1;
        }

        return $this->name2index[$nameOrIndex] ?? -1;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /****************************************************************
     * helper methods
     ***************************************************************/

    /**
     * @return array
     */
    public function getArgsHelpLines(): array
    {
        $helpData = [];
        foreach ($this->argDefines as $define) {
            $name = $define['name'];

            $helpData[$name] = $define['desc'];
        }

        return $helpData;
    }

    /**
     * @return array
     */
    public function getOptsHelpLines(): array
    {
        $helpLines = [];
        foreach ($this->optDefines as $name => $opt) {
            if ($this->showHiddenOpt === false && $opt['hidden']) {
                continue;
            }

            [$helpName, $fmtDesc] = $this->buildOptHelpLine($name, $opt);
            $helpLines[$helpName]  = $fmtDesc;
        }

        ksort($helpLines);
        return $helpLines;
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
        $index = $this->getArgIndex($nameOrIndex);

        return isset($this->args[$index]);
    }

    /**
     * @param int|string $nameOrIndex
     *
     * @return bool
     */
    public function hasDefineArg(int|string $nameOrIndex): bool
    {
        $index = $this->getArgIndex($nameOrIndex);

        return $index > -1;
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

        return $this->argDefines[$index];
    }

    /**
     * @return array
     */
    public function getArgDefines(): array
    {
        return $this->argDefines;
    }

    /**
     * Whether input argument
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasInputOpt(string $name): bool
    {
        return isset($this->opts[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasDefineOpt(string $name): bool
    {
        return isset($this->optDefines[$name]);
    }

    /**
     * @return array
     */
    public function getOptDefines(): array
    {
        return $this->optDefines;
    }
}
