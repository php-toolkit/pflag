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
use Toolkit\Stdlib\Str;
use function is_scalar;
use function is_string;
use function strtoupper;
use function trim;

/**
 * Class FlagConst
 *
 * @package Toolkit\PFlag
 */
class FlagType
{
    public const INT = 'int';

    public const BOOL = 'bool';

    public const FLOAT = 'float';

    public const STRING = 'string';

    // ------ complex types ------

    public const ARRAY  = 'array';

    public const OBJECT   = 'object';

    public const CALLABLE = 'callable';

    // ------ extend types ------

    public const INTS = 'ints';

    public const STRINGS = 'strings';

    public const MIXED = 'mixed';

    public const CUSTOM = 'custom';

    public const UNKNOWN = 'unknown';

    public const ARRAY_TYPES = [
        self::ARRAY   => 2,
        self::INTS    => 3,
        self::STRINGS => 3,
    ];

    public const TYPES_MAP = [
        self::INT      => 1,
        self::BOOL     => 1,
        self::FLOAT    => 1,
        self::STRING   => 1,

        // ------ complex types ------
        self::ARRAY    => 2,
        self::OBJECT   => 2,
        self::CALLABLE => 2,

        // ------ extend types ------
        self::INTS     => 3,
        self::STRINGS  => 3,
        self::MIXED    => 3,
        self::CUSTOM   => 3,
        self::UNKNOWN  => 3,
    ];

    public const TYPE_HELP_NAME = [
        // self::INTS    => 'int...',
        // self::STRINGS => 'string...',
        self::ARRAY   => '',
        self::BOOL    => '',
    ];

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return isset(self::TYPES_MAP[$type]);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function isArray(string $type): bool
    {
        return isset(self::ARRAY_TYPES[$type]);
    }

    /**
     * @param string $type
     * @param bool   $toUpper
     *
     * @return string
     */
    public static function getHelpName(string $type, bool $toUpper = true): string
    {
        $name = self::TYPE_HELP_NAME[$type] ?? $type;

        return $toUpper ? strtoupper($name) : $name;
    }

    /**
     * Get type default value.
     *
     * @param string $type
     *
     * @return array|false|float|int|string|null
     */
    public static function getDefault(string $type)
    {
        $value = null;
        switch ($type) {
            case self::INT:
                $value = 0;
                break;
            case self::BOOL:
                $value = false;
                break;
            case self::FLOAT:
                $value = (float)0;
                break;
            case self::STRING:
                $value = '';
                break;
            case self::INTS:
            case self::ARRAY:
            case self::STRINGS:
                $value = [];
                break;
            default:
                // nothing
                break;
        }

        return $value;
    }

    /**
     * @param string $type
     * @param mixed  $value
     *
     * @return bool|float|int|mixed|string
     */
    public static function fmtBasicTypeValue(string $type, $value)
    {
        if (!is_scalar($value)) {
            return $value;
        }

        // format value by type
        switch ($type) {
            case self::INT:
            case self::INTS:
                $value = (int)$value;
                break;
            case self::BOOL:
                if (is_string($value)) {
                    // $value = FlagHelper::str2bool($value);
                    $value = Str::toBool2($value);
                } else {
                    $value = (bool)$value;
                }
                break;
            case self::FLOAT:
                $value = (float)$value;
                break;
            case self::STRING:
            case self::STRINGS:
                $value = (string)$value;
                break;
            default:
                // nothing
                break;
        }

        return $value;
    }

    /**
     * Convert string to array
     *
     * - eg: '23, 45' => [23, 45]
     * - eg: 'a, b' => ['a', 'b']
     * - eg: '[a, b]' => ['a', 'b']
     *
     * @param string $type
     * @param string $str
     *
     * @return array|string
     */
    public static function str2ArrValue(string $type, string $str)
    {
        switch ($type) {
            case self::INTS:
                $value = Str::toInts(trim($str, '[] '));
                break;
            case self::ARRAY:
            case self::STRINGS:
                $value = Str::toArray(trim($str, '[] '));
                break;
            default:
                $value = $str;
                break;
        }

        return $value;
    }
}
