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
use Toolkit\Stdlib\Arr;
use Toolkit\Stdlib\Str;
use function array_slice;
use function current;
use function escapeshellarg;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function next;
use function preg_match;
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
class SFlags extends AbstractParser
{
    public const SHORT_STYLE_GUN = 'gnu';

    public const SHORT_STYLE_POSIX = 'posix';

    public const REQUIRED = 'required';

    private const TRIM_CHARS = ", \t\n\r\0\x0B";

    public const DEFINE_ITEM = [
        'name'     => '',
        'desc'     => '',
        'type'     => FlagType::STRING,
        // 'index'    => 0, // only for argument
        // 'aliases'  => 0, // only for option
        'required' => false,
        'default'  => null, // TODO allow default value.
        // 'validator' => callable
    ];

    /**
     * @var self
     */
    private static $std;

    // ------------------------ opts ------------------------

    /**
     * The options rules
     * - type see FlagType::*
     *
     * ```php
     * [
     *  // v: only value, as name and use default type FlagType::STRING
     *  // k-v: key is name, value can be string|array
     *  //  - string value is rule(type,required,default,desc).
     *  //  - array is define item self::DEFINE_ITEM
     *  'long,s',
     *  // name => rule
     *  // TIP: name 'long,s' - first is the option name. remaining is aliases.
     *  'long,s' => int,
     *  's'      => bool,
     *  'long'   => string,
     *  'long'   => array, // can also: int[], string[]
     *  'name'   => 'type,required,default,the description message', // TODO with default, desc, required
     * ]
     * ```
     *
     * @var array
     */
    private $optRules = [];

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
     * The arguments rules
     *
     * ```php
     * [
     *  // v: only value, as rule - use default type FlagType::STRING
     *  // k-v: key is name, value is rule(type,required,default,desc).
     *  // - type see FlagType::*
     *  'type',
     *  'name' => 'type',
     *  'name' => 'type,required', // arg option
     *  'name' => 'type,required,default,the description message', // TODO with default, desc, required
     * ]
     * ```
     *
     * @var array
     */
    private $argRules = [];

    /**
     * The arguments definitions
     *
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
     * @return $this
     */
    public static function std(): self
    {
        if (!self::$std) {
            self::$std = new self();
        }

        return self::$std;
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
     * @return self
     */
    public function parseDefined(array $rawFlags, array $optRules, array $argRules = []): self
    {
        if ($this->parsed) {
            return $this;
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
     * @link http://php.net/manual/zh/function.getopt.php#83414
     *
     * @param array $rawFlags
     *
     * @return self
     */
    public function parse(array $rawFlags): self
    {
        if ($this->parsed) {
            return $this;
        }

        $this->parsed   = true;
        $this->rawFlags = $rawFlags;
        $this->parseOptRules($this->optRules);

        $optParseEnd = false;
        while (false !== ($p = current($rawFlags))) {
            next($rawFlags);

            // option parse end, collect remaining arguments.
            if ($optParseEnd) {
                $this->rawArgs[] = $p;
                continue;
            }

            // is options and not equals '-' '--'
            if ($p !== '' && $p[0] === '-' && '' !== trim($p, '-')) {
                $value  = true; // bool option value default is true.
                $hasVal = false;

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
                    throw new FlagException("flag option provided but not defined: $p", 404);
                }

                $define = $this->optDefines[$option];
                // option value data type
                $type = $define['type'];

                // only allow set bool value by inline. eg: -o=false
                $isBool = $type === FlagType::BOOL;
                if ($hasVal && $isBool) {
                    $this->setRealOptValue($option, $type, $value);
                    continue;
                }

                // check if next element is a descriptor or a value
                $next = current($rawFlags);
                if ($hasVal === false && $isBool === false) {
                    if (false === FlagHelper::isOptionValue($next)) {
                        throw new FlagException("must provide value for the option: $option", 404);
                    }

                    $value = $next;
                    next($rawFlags);
                }

                $this->setRealOptValue($option, $type, $value);
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

        // check required opts
        if ($this->requiredOpts) {
            foreach ($this->requiredOpts as $name) {
                if (!isset($this->opts[$name])) {
                    throw new FlagException("flag option '$name' is required");
                }
            }
        }

        // parse defined arguments
        if ($this->argRules) {
            $this->parseDefinedArgs($this->argRules);
        }

        return $this;
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
    public function setOptValue(string $option, $value): void
    {
        $option = $this->resolveAlias($option);
        if (!isset($this->optDefines[$option])) {
            throw new FlagException("flag option provided but not defined: $option", 404);
        }

        $define = $this->optDefines[$option];
        $this->setRealOptValue($option, $define['type'], $value);
    }

    /**
     * @param string $option
     * @param string $type
     * @param mixed  $value
     */
    protected function setRealOptValue(string $option, string $type, $value): void
    {
        $value = FlagType::fmtBasicTypeValue($type, $value);

        if (FlagType::isArray($type)) {
            $this->opts[$option][] = $value;
        } else {
            $this->opts[$option] = $value;
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
     *
     * ```php
     * $defines = [
     *  // type see FlagType::*. eg {@see FlagType::INT, FlagType::STRING}
     *  'type', // not set name, use index for get value.
     *   // allow set argument name by string key.
     *  'name' => 'type',
     *  'name' => 'type,required', // allow add limit settings: required
     * ];
     * ```
     *
     * @param array $argRules
     */
    public function parseDefinedArgs(array $argRules): void
    {
        // parse arguments
        $args = $this->parseRawArgs();

        // init with default value.
        $hasArrayArg = $hasOptional = false;

        // check and collect arguments
        $index = 0;
        foreach ($argRules as $name => $rule) {
            if (!$rule) {
                throw new FlagException('flag argument rule cannot be empty');
            }

            // parse rule
            $define = $this->parseRule($rule, is_string($name) ? $name : '', $index, false);

            // set argument name
            $hasName = $this->setArgName($index, $name = $define['name']);
            $isArray = FlagType::isArray($define['type']);
            $nameMsg = $hasName ? "($name)" : '';

            // NOTICE: only allow one array argument and must be at last.
            if ($hasArrayArg && $isArray) {
                throw new FlagException("cannot add argument #$index$nameMsg after an array argument");
            }

            $required = $define['required'];
            if ($hasOptional && $required) {
                throw new FlagException("cannot add a required argument #$index$nameMsg after an optional one");
            }

            if ($required && !isset($args[$index])) {
                throw new FlagException("flag argument #$index$nameMsg is required");
            }

            $hasArrayArg = $hasArrayArg || $isArray;
            $hasOptional = $hasOptional || !$required;

            // collect value
            if ($isArray) {
                $this->args[] = array_slice($args, $index); // remain args
            } else {
                $this->args[] = $args[$index];
            }

            $index++;
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
        $this->parsed  = false;
        $this->rawArgs = $this->rawFlags = [];

        $this->opts = $this->args = [];

        if ($resetDefines) {
            $this->optRules = $this->optDefines = [];
            $this->argRules = $this->argDefines = [];
        }
    }

    /****************************************************************
     * parse rule to definition
     ***************************************************************/

    /**
     * Load option defines rules
     *
     * @param array $rules rule please {@see $optRules}
     */
    protected function parseOptRules(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            if (is_int($key)) { // only name.
                $key  = (string)$rule;
                $rule = FlagType::STRING;
            } else {
                $key = (string)$key;
            }

            $define = $this->parseRule($rule, $key);

            // save parse definition
            $this->optDefines[$define['name']] = $define;
        }
    }

    /**
     * Parse option name and aliases
     *
     * @param string $key 'lang,s' => option name is 'lang', alias 's'
     *
     * @return array [name, aliases]
     */
    protected function parseRuleOptName(string $key): array
    {
        $key = trim($key, self::TRIM_CHARS);
        if (!$key) {
            throw new FlagException('flag option name cannot be empty');
        }

        // only name.
        if (strpos($key, ',') === false) {
            return [$key, []];
        }

        $name = '';
        $keys = Str::explode($key, ',');

        // TIP: first is the option name. remaining is aliases.
        $aliases = [];
        foreach ($keys as $i => $k) {
            if ($i === 0) {
                $name = $k;
            } else {
                $aliases[] = $k;
                $this->setAlias($name, $k, true);
            }
        }

        return [$name, $aliases];
    }

    /**
     * Parse rule
     *
     * **array rule**
     *
     * - will merge an {@see DEFINE_ITEM}
     *
     * **string rule**
     *
     * - full rule: 'type,required,default,desc'
     * - rule item position is fixed.
     * - if ignore `type`, will use default type: string.
     *
     * can ignore item use empty:
     * - 'type' - only set type.
     * - 'type,,,desc' - not set required,default
     *
     * @param string|array $rule
     * @param string       $name
     * @param int          $index
     * @param bool         $isOption
     *
     * @return array {@see DEFINE_ITEM}
     * @see $argRules
     * @see $optRules
     */
    protected function parseRule($rule, string $name = '', int $index = 0, bool $isOption = true): array
    {
        if (is_array($rule)) {
            $item = Arr::replace(self::DEFINE_ITEM, $rule);
        } else { // parse string rule.
            $item = self::DEFINE_ITEM;
            $rule = trim((string)$rule, self::TRIM_CHARS);

            if (strpos($rule, ',') === false) {
                $item['type'] = $rule;
            } else { // eg: 'type,required,default,desc'
                $nodes = Str::splitTrimmed($rule, ',', 4);

                // first is type.
                $item['type'] = $nodes[0];
                // second is required
                $item['required'] = $nodes[1] === self::REQUIRED;

                // more: default, desc
                if (isset($nodes[2]) && $nodes[2] !== '') {
                    $item['default'] = $nodes[2];
                }
                if (!empty($nodes[3])) {
                    $item['desc'] = $nodes[3];
                }
            }
        }

        $name = $name ?: $item['name'];
        if ($isOption) {
            // parse option name.
            [$name, $aliases] = $this->parseRuleOptName($name);

            // save alias
            $item['aliases'] = $aliases;
            if ($item['required']) {
                $this->requiredOpts[] = $name;
            }
        } else {
            $item['index'] = $index;
        }

        // check type
        if (!FlagType::isValid($type = $item['type'])) {
            $nameMark = $name ? "(name: $name)" : "(#$index)";
            throw new FlagException("cannot define invalid flag type: $type$nameMark");
        }

        $item['name'] = $name;
        return $item;
    }

    /****************************************************************
     * get option and argument value
     ***************************************************************/

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return isset($this->opts[$name]);
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
        return $this->opts[$name] ?? $default;
    }

    /**
     * @return array
     */
    public function getOpts(): array
    {
        return $this->opts;
    }

    /**
     * @param string     $nameOrIndex
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function getArgument(string $nameOrIndex, $default = null)
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
        if (is_string($nameOrIndex)) {
            if (!isset($this->name2index[$nameOrIndex])) {
                throw new FlagException("flag argument name '$nameOrIndex' is undefined");
            }

            $index = $this->name2index[$nameOrIndex];
        } else {
            $index = (int)$nameOrIndex;
        }

        return $this->args[$index] ?? $default;
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
     * @param string $nameOrIndex
     *
     * @return int
     */
    public function getArgIndex(string $nameOrIndex): int
    {
        if (!is_string($nameOrIndex)) {
            return (int)$nameOrIndex;
        }

        if (!isset($this->name2index[$nameOrIndex])) {
            throw new FlagException("flag argument name '$nameOrIndex' is undefined");
        }

        return $this->name2index[$nameOrIndex];
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
     * Escapes a token through escape shell arg if it contains unsafe chars.
     *
     * @param string $token
     *
     * @return string
     */
    public static function escapeToken(string $token): string
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }

    /**
     * @return array
     */
    public function getArgRules(): array
    {
        return $this->argRules;
    }

    /**
     * @param array $argRules
     */
    public function setArgRules(array $argRules): void
    {
        $this->argRules = $argRules;
    }

    /**
     * @return array
     */
    public function getArgDefines(): array
    {
        return $this->argDefines;
    }

    /**
     * @return array
     */
    public function getOptRules(): array
    {
        return $this->optRules;
    }

    /**
     * @param array $optRules
     */
    public function setOptRules(array $optRules): void
    {
        $this->optRules = $optRules;
    }

    /**
     * @return array
     */
    public function getOptDefines(): array
    {
        return $this->optDefines;
    }
}
