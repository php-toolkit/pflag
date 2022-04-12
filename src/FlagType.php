<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use Toolkit\PFlag\Exception\FlagException;
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
    public static function getDefault(string $type): float|bool|int|array|string|null
    {
        return match ($type) {
            self::INT => 0,
            self::BOOL => false,
            self::FLOAT => 0.0,
            self::STRING => '',
            self::INTS, self::ARRAY, self::STRINGS => [],
            default => null,
        };
    }

    /**
     * @param string $type
     * @param mixed  $value
     *
     * @return mixed
     */
    public static function fmtBasicTypeValue(string $type, mixed $value): mixed
    {
        if (!is_scalar($value)) {
            return $value;
        }

        // convert to bool
        if ($type === self::BOOL) {
            $value = is_string($value) ? Str::tryToBool($value) : (bool)$value;

            if (is_string($value)) {
                throw new FlagException("convert value '$value' to bool failed");
            }
            return $value;
        }

        // format value by type
        return match ($type) {
            self::INT, self::INTS => (int)$value,
            // self::BOOL => is_string($value) ? Str::toBool2($value) : (bool)$value,
            self::FLOAT => (float)$value,
            self::STRING, self::STRINGS => (string)$value,
            default => $value,
        };
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
    public static function str2ArrValue(string $type, string $str): array|string
    {
        return match ($type) {
            self::INTS => Str::toInts(trim($str, '[] ')),
            self::ARRAY, self::STRINGS => Str::toArray(trim($str, '[] ')),
            default => $str,
        };
    }
}
