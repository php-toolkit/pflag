<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use function array_map;
use function array_shift;
use function basename;
use function implode;
use function is_numeric;
use function ltrim;
use function strlen;

/**
 * class FlagUtil
 */
class FlagUtil
{
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
        return $val1 > $val2 ? $val1 : $val2;
    }

    /**
     * @return string
     */
    public static function getBinName(): string
    {
        $script = '';
        if (isset($_SERVER['argv']) && ($argv = $_SERVER['argv'])) {
            $script = array_shift($argv);
        }

        return basename($script);
    }
}
