<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use function array_map;
use function implode;
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
     * @param int $val1
     * @param int $val2
     *
     * @return int
     */
    public static function getMaxInt(int $val1, int $val2): int
    {
        return $val1 > $val2 ? $val1 : $val2;
    }
}
