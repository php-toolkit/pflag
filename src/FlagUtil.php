<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use function array_keys;
use function array_map;
use function array_shift;
use function basename;
use function escapeshellarg;
use function explode;
use function implode;
use function is_numeric;
use function ltrim;
use function preg_match;
use function str_replace;
use function strlen;
use function trim;

/**
 * class FlagUtil
 */
class FlagUtil
{
    private static ?string $scriptName = null;

    /**
     * @param array $names
     *
     * @return string
     */
    public static function buildOptHelpName(array $names): string
    {
        $nodes = array_map(static function (string $name) {
            return (strlen($name) > 1 ? '--' : '-') . $name;
        }, $names);

        return implode(', ', $nodes);
    }

    /**
     * check and get option Name
     *
     * valid:
     * `-a`
     * `-b=value`
     * `--long`
     * `--long=value1`
     *
     * invalid:
     * - empty string
     * - no prefix '-' (is argument)
     * - invalid option name as argument. eg: '-9' '--34' '- '
     *
     * @param string $val
     *
     * @return string
     */
    public static function filterOptionName(string $val): string
    {
        // is not an option.
        if ('' === $val || $val[0] !== '-') {
            return '';
        }

        $name = ltrim($val, '- ');
        if (is_numeric($name)) {
            return '';
        }

        return $name;
    }

    /**
     * @param int $val1
     * @param int $val2
     *
     * @return int
     */
    public static function getMaxInt(int $val1, int $val2): int
    {
        return max($val1, $val2);
    }

    /**
     * @param bool $refresh
     *
     * @return string
     */
    public static function getBinName(bool $refresh = false): string
    {
        if (!$refresh && self::$scriptName !== null) {
            return self::$scriptName;
        }

        $scriptName = '';
        if (isset($_SERVER['argv']) && ($argv = $_SERVER['argv'])) {
            $scriptFile = array_shift($argv);
            $scriptName = basename($scriptFile);
        }

        self::$scriptName = $scriptName;
        return self::$scriptName;
    }

    /**
     * check input is valid option value
     *
     * @param mixed $val
     *
     * @return bool
     */
    public static function isOptionValue(mixed $val): bool
    {
        if ($val === false) {
            return false;
        }

        // if is: '', 0 || is not option name
        if (!$val || $val[0] !== '-') {
            return true;
        }

        // ensure is option value.
        if (!str_contains($val, '=')) {
            return true;
        }

        // is string value, but contains '='
        [$name,] = explode('=', $val, 2);

        // named argument OR invalid: 'some = string'
        return false === self::isValidName($name);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('#^[a-zA-Z_][\w-]{0,36}$#', $name) === 1;
    }

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
     * Align command option names.
     *
     * @param array $options
     *
     * @return array
     */
    public static function alignOptions(array $options): array
    {
        if (!$options) {
            return [];
        }

        // check has short option. e.g '-h, --help'
        $nameString = '|' . implode('|', array_keys($options));
        if (preg_match('/\|-\w/', $nameString) !== 1) {
            return $options;
        }

        $formatted = [];
        foreach ($options as $name => $des) {
            if (!$name = trim($name, ', ')) {
                continue;
            }

            // start with '--', padding length equals to '-h, '
            if (isset($name[1]) && $name[1] === '-') {
                $name = '    ' . $name;
            } else {
                $name = str_replace([',-'], [', -'], $name);
            }

            $formatted[$name] = $des;
        }

        return $formatted;
    }
}
