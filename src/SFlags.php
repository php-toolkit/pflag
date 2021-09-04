<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\Stdlib\OS;
use function array_slice;
use function current;
use function explode;
use function is_int;
use function is_string;
use function next;
use function str_split;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Class SFlags
 *
 * @package Toolkit\PFlag
 */
class SFlags extends AbstractFlags
{
    /**
     * @var self
     */
    private static $std;

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
    private $optDefines = [];

    /**
     * Parsed option values
     *
     * ```php
     * [name => value]
     * ```
     *
     * @var array
     */
    private $opts = [];

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
    private $argDefines = [];

    /**
     * The mapping argument name to index
     *
     * @var array
     */
    private $name2index = [];

    /**
     * Parsed argument values
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
    private $args = [];

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
        return $this->doBuildHelp($this->argDefines, $this->optDefines, $withColor);
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
        $this->parseOptRules($this->optRules);
        $this->parseArgRules($this->argRules);

        $optParseEnd = false;
        $parseStatus = self::STATUS_OK;
        while (false !== ($p = current($flags))) {
            next($flags);

            // option parse end, collect remaining arguments.
            if ($optParseEnd) {
                $this->rawArgs[] = $p;
                continue;
            }

            // is options and not equals '-' '--'
            if ($p !== '' && $p[0] === '-' && '' !== trim($p, '-')) {
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
                if (strpos($option, '-') === 0) {
                    $isShort = false;
                    $option  = substr($option, 1);

                    // long-opt: value specified inline (--<opt>=<value>)
                    if (strpos($option, '=') !== false) {
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

                    throw new FlagException("flag option provided but not defined: $p", 404);
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
                    if (false === FlagHelper::isOptionValue($next)) {
                        throw new FlagException("must provide value for the option: $option", 404);
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

        // parse defined arguments
        if ($this->argDefines) {
            $this->parseDefinedArgs();
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
     * @param mixed  $value
     */
    protected function setOptValue(string $option, $value): void
    {
        $option = $this->resolveAlias($option);
        if (!isset($this->optDefines[$option])) {
            throw new FlagException("flag option provided but not defined: $option", 404);
        }

        $define = $this->optDefines[$option];
        $this->setRealOptValue($option, $value, $define);
    }

    /**
     * @param string $name   The option name
     * @param mixed  $value
     * @param array  $define {@see DEFINE_ITEM}
     */
    protected function setRealOptValue(string $name, $value, array $define): void
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
    public function parseDefinedArgs(): void
    {
        // parse arguments
        $args = $this->parseRawArgs($this->rawArgs);

        // check and collect argument values
        foreach ($this->argDefines as $index => $define) {
            $name = $define['name'];
            $mark = $name ? "#$index($name)" : "#$index";

            $required = $define['required'];
            $isArray  = FlagType::isArray($define['type']);

            if ($required && !isset($args[$index])) {
                throw new FlagException("flag argument $mark is required");
            }

            // collect value
            if ($isArray) {
                $arrValues = array_slice($args, $index); // remain args
                foreach ($arrValues as $arrValue) {
                    $this->collectArgValue($arrValue, $index, true, $define);
                }
            } else {
                $value = $args[$index];
                $this->collectArgValue($value, $index, false, $define);
            }
        }
    }

    /**
     * @param mixed $value
     * @param int   $index
     * @param bool  $isArray
     * @param array $define
     */
    protected function collectArgValue($value, int $index, bool $isArray, array $define): void
    {
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
     * @param int          $index
     * @param string|mixed $name
     *
     * @return bool
     */
    protected function setArgName(int $index, $name): bool
    {
        if (!$name || !is_string($name)) {
            return false;
        }

        if (isset($this->name2index[$name])) {
            throw new FlagException("cannot repeat define flag argument '$name'");
        }

        $this->name2index[$name] = $index;
        return true;
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

    /****************************************************************
     * parse rule to definition
     ***************************************************************/

    /**
     * Parse option rules
     *
     * @param array $rules rule please {@see optRules}
     */
    protected function parseOptRules(array $rules): void
    {
        /**
         * @var string|int $key
         */
        foreach ($rules as $key => $rule) {
            if (is_int($key)) { // only name.
                $key  = (string)$rule;
                $rule = FlagType::STRING;
            } else {
                $key = (string)$key;
            }

            // parse rule
            $define = $this->parseRule($rule, $key);

            $type = $define['type'];
            $name = $define['name'];

            // has default value
            if (isset($define['default'])) {
                if ($define['required']) {
                    throw new FlagException("cannot set a default value, if flag is required. flag: $name");
                }

                $default = FlagType::fmtBasicTypeValue($type, $define['default']);

                // save as value.
                $this->opts[$name] = $define['default'] = $default;
            }

            // support read value from ENV var
            if ($define['envVar'] && ($envVal = OS::getEnvVal($define['envVar']))) {
                $this->opts[$name] = FlagType::fmtBasicTypeValue($type, $envVal);
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
    }

    /**
     * Parse argument rules
     *
     * @param array $rules rule please {@see argRules}
     */
    protected function parseArgRules(array $rules): void
    {
        // init with default value.
        $hasArrayArg = $hasOptional = false;

        // check and collect arguments
        $index = 0;
        foreach ($rules as $name => $rule) {
            if (!$rule) {
                throw new FlagException('flag argument rule cannot be empty');
            }

            // parse rule
            $define = $this->parseRule($rule, is_string($name) ? $name : '', $index, false);

            // set argument name
            $this->setArgName($index, $name = $define['name']);
            $type = $define['type'];
            $mark = $name ? "#$index($name)" : "#$index";

            // has default value
            $required = $define['required'];
            if (isset($define['default'])) {
                $default = FlagType::fmtBasicTypeValue($type, $define['default']);

                // save as value
                $this->args[$index] = $define['default'] = $default;

                if ($required) {
                    throw new FlagException("cannot set a default value, if flag is required. flag: $mark");
                }
            }

            // NOTICE: only allow one array argument and must be at last.
            $isArray = FlagType::isArray($define['type']);
            if ($hasArrayArg && $isArray) {
                throw new FlagException("cannot add argument $mark after an array argument");
            }

            if ($hasOptional && $required) {
                throw new FlagException("cannot add a required argument $mark after an optional one");
            }

            $hasArrayArg = $hasArrayArg || $isArray;
            $hasOptional = $hasOptional || !$required;

            // save define
            $this->argDefines[] = $define;
            $index++;
        }
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
        return isset($this->opts[$name]);
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
     * @param string     $name
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getOption(string $name, $default = null)
    {
        return $this->getOpt($name, $default);
    }

    /**
     * @param string     $name
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getOpt(string $name, $default = null)
    {
        if (isset($this->opts[$name])) {
            return $this->opts[$name];
        }

        $define = $this->optDefines[$name] ?? [];
        if (!$define) { // not exist option
            throw new FlagException("flag option '$name' is undefined");
        }

        return $default ?? FlagType::getDefault($define['type']);
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
    public function hasArg($nameOrIndex): bool
    {
        $index = $this->getArgIndex($nameOrIndex);

        return $index > -1 && isset($this->args[$index]);
    }

    /**
     * @param int|string $nameOrIndex
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getArgument($nameOrIndex, $default = null)
    {
        return $this->getArg($nameOrIndex, $default);
    }

    /**
     * @param int|string $nameOrIndex
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getArg($nameOrIndex, $default = null)
    {
        $index = $this->getArgIndex($nameOrIndex);
        if ($index < 0) {
            throw new FlagException("flag argument '$nameOrIndex' is undefined");
        }

        if (isset($this->args[$index])) {
            return $this->args[$index];
        }

        // get default with type format
        $define = $this->argDefines[$index];
        return $default ?? FlagType::getDefault($define['type']);
    }

    /**
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getFirstArg($default = null)
    {
        return $this->getArg(0, $default);
    }

    /**
     * @param string|int $nameOrIndex
     *
     * @return int Will return -1 if arg not exists
     */
    protected function getArgIndex($nameOrIndex): int
    {
        if (!is_string($nameOrIndex)) {
            $index = (int)$nameOrIndex;
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
     * @param string|int $nameOrIndex
     *
     * @return bool
     */
    public function hasDefineArg($nameOrIndex): bool
    {
        $index = $this->getArgIndex($nameOrIndex);

        return isset($this->argDefines[$index]);
    }

    /**
     * @return array
     */
    public function getArgDefines(): array
    {
        return $this->argDefines;
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
