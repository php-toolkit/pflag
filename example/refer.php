<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

$flags = ['--name', 'inhere', '--tags', 'php', '-t', 'go', '--tags', 'java', '-f', 'arg0'];
echo 'count: ', count($flags), "\n";

$cur = current($flags);
$key = key($flags);
echo "key: $key, current: $cur\n";

while (true) {
    if (($key = key($flags)) === null) {
        break;
    }

    $val = next($flags);
    echo "key: $key, next: '$val'\n";
}
