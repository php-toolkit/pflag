<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Concern;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\FlagType;
use Toolkit\Stdlib\Arr;
use Toolkit\Stdlib\Str;
use function array_shift;
use function array_unshift;
use function is_array;
use function is_int;
use function is_numeric;
use function ltrim;
use function strlen;
use function strpos;
use function trim;

/**
 * trait RuleParserTrait
 */
trait RuleParserTrait
{

    // -------------------- rules --------------------

    /**
     * The options rules
     *
     * **rule item**
     * - array  It is define item, see {@see FlagsParser::DEFINE_ITEM}
     * - string Value is rule(format: `type;desc;required;default;shorts`)
     *
     * **data type**
     *
     * - type see FlagType::*
     * - default type is FlagType::STRING
     *
     * ```php
     * [
     *  // v: only value, as name and use default type
     *  // k-v: key is name, value can be string|array
     *  //  - string value is rule
     *  //  - array is define item self::DEFINE_ITEM
     *  'long,s',
     *  // name => rule
     *  // TIP: name 'long,s' - first is the option name. remaining is shorts.
     *  'long,s' => int,
     *  'f'      => bool,
     *  'long'   => string,
     *  'tags'   => array, // can also: ints, strings
     *  'name'   => 'type;the description message;required;default', // with desc, default, required
     * ]
     * ```
     *
     * @var array
     */
    protected array $optRules = [];

    /**
     * The arguments rules
     *
     * **rule item**
     * - array  It is define item, see {@see FlagsParser::DEFINE_ITEM}
     * - string Value is rule(format: `type;desc;required;default;shorts`)
     *
     * **data type**
     *
     * - type see FlagType::*
     * - default type is FlagType::STRING
     *
     * ```php
     * [
     *  // v: only value, as rule and use default type
     *  // k-v: key is name, value is rule
     *  'type',
     *  'name' => 'type',
     *  'name' => 'type;required', // arg option
     *  'name' => 'type;the description message;required;default', // with default, desc, required
     * ]
     * ```
     *
     * @var array
     */
    protected array $argRules = [];

    /****************************************************************
     * add rule methods
     ***************************************************************/

    /**
     * @param array $rules see {@see optRules} for each rule.
     */
    public function addOptsByRules(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            if (is_int($name)) { // only name.
                $name = (string)$rule;
                $rule = FlagType::STRING;
            } else {
                $name = (string)$name;
            }

            $this->addOptByRule($name, $rule);
        }
    }

    /**
     * Add and option by rule
     *
     * @param string $name
     * @param array|string $rule {@see optRules}
     *
     * @return static
     */
    public function addOptByRule(string $name, array|string $rule): static
    {
        $this->optRules[$name] = $rule;
        return $this;
    }

    /**
     * @param array $rules
     *
     * @see addArgByRule()
     */
    public function addArgsByRules(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            if (!$rule) {
                throw new FlagException('flag argument rule cannot be empty');
            }

            $this->addArgByRule((string)$name, $rule);
        }
    }

    /**
     * Add and argument by rule
     *
     * @param string $name
     * @param array|string $rule please see {@see argRules}
     *
     * @return static
     */
    public function addArgByRule(string $name, array|string $rule): static
    {
        if ($name && !is_numeric($name)) {
            $this->argRules[$name] = $rule;
        } else {
            $this->argRules[] = $rule;
        }

        return $this;
    }

    /****************************************************************
     * parse rule to definition
     ***************************************************************/

    /**
     * Parse rule
     *
     * **array rule**
     *
     * - will merge an {@see FlagsParser::DEFINE_ITEM}
     *
     * **string rule**
     *
     * - full rule. (format: 'type;desc;required;default;shorts')
     * - rule item position is fixed.
     * - if ignore `type`, will use default type: string.
     *
     * can ignore item use empty:
     * - 'type' - only set type.
     * - 'type;desc;;' - not set required,default
     * - 'type;;;default' - not set required,desc
     *
     * @param array|string $rule
     * @param string $name
     * @param int $index
     * @param bool $isOption
     *
     * @return array {@see FlagsParser::DEFINE_ITEM}
     * @see argRules
     * @see optRules
     */
    protected function parseRule(array|string $rule, string $name = '', int $index = 0, bool $isOption = true): array
    {
        if (!$rule) {
            $rule = FlagType::STRING;
        }

        $shortsFromRule = [];
        if (is_array($rule)) {
            $item = Arr::replace(FlagsParser::DEFINE_ITEM, $rule);
            // set alias by array item
            $shortsFromRule = $item['shorts'];
        } else { // parse string rule.
            $sep  = FlagsParser::RULE_SEP;
            $item = FlagsParser::DEFINE_ITEM;
            $rule = trim((string)$rule, FlagsParser::TRIM_CHARS);

            // not found sep char.
            if (!str_contains($rule, $sep)) {
                // has multi words, is an desc string.
                if (strpos($rule, ' ') > 0) {
                    $item['desc'] = $rule;
                } else { // only type name.
                    $item['type'] = $rule;
                }
            } else { // has multi node. eg: 'type;desc;required;default;shorts'
                $limit = $isOption ? 5 : 4;
                $nodes = Str::splitTrimmed($rule, $sep, $limit);

                // optimize: has multi words, is an desc. auto padding type: string
                if (strpos($nodes[0], ' ') > 1) {
                    array_unshift($nodes, FlagType::STRING);
                }

                // first is type.
                $item['type'] = $nodes[0];

                // second is desc
                if (!empty($nodes[1])) {
                    $item['desc'] = $nodes[1];
                }

                // required
                $item['required'] = false;
                if (isset($nodes[2]) && ($nodes[2] === 'required' || Str::toBool($nodes[2]))) {
                    $item['required'] = true;
                }

                // default
                if (isset($nodes[3]) && $nodes[3] !== '') {
                    $item['default'] = FlagType::str2ArrValue($nodes[0], $nodes[3]);
                }

                // for option: shorts
                if ($isOption && isset($nodes[4]) && $nodes[4] !== '') {
                    $shortsFromRule = Str::explode($nodes[4], ',');
                }
            }
        }

        $name = $name ?: $item['name'];
        if ($isOption) {
            // parse option name.
            [$name, $shorts, $aliases] = $this->parseRuleOptName($name);

            // save shorts and aliases
            $item['shorts']  = $shorts ?: $shortsFromRule;
            $item['aliases'] = $aliases;
        } else {
            $item['index'] = $index;
        }

        $item['name'] = $name;
        return $item;
    }

    /**
     * Parse option name and shorts
     *
     * @param string $key 'lang,s' => option name is 'lang', alias 's'
     *
     * @return array{string, array, array} [name, shorts, aliases]
     */
    protected function parseRuleOptName(string $key): array
    {
        $key = trim($key, FlagsParser::TRIM_CHARS);
        if (!$key) {
            throw new FlagException('flag option name cannot be empty');
        }

        // only name.
        if (!str_contains($key, ',')) {
            $name = ltrim($key, '-');
            return [$name, [], []];
        }

        $name = '';
        $keys = Str::explode($key, ',');

        $shorts = $aliases = [];
        foreach ($keys as $k) {
            // support like '--name, -n'
            $k = ltrim($k, '-');

            // max length string as option name.
            if (($kl = strlen($k)) > 1) {
                if (!$name) {
                    $name = $k;
                } elseif ($kl > strlen($name)) {
                    $aliases[] = $name;
                    // update name
                    $name = $k;
                } else {
                    $aliases[] = $k;
                }
                continue;
            }

            // one char, as shorts
            $shorts[] = $k;
        }

        // no long name, first short name as option name.
        if (!$name) {
            $name = array_shift($shorts);
        }

        return [$name, $shorts, $aliases];
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
     *
     * @see optRules
     */
    public function setOptRules(array $optRules): void
    {
        $this->addOptsByRules($optRules);
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
     *
     * @see argRules
     */
    public function setArgRules(array $argRules): void
    {
        $this->addArgsByRules($argRules);
    }
}
