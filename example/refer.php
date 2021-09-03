<?php

$flags = ['--name', 'inhere', '--tags', 'php', '-t', 'go', '--tags', 'java', '-f', 'arg0'];
echo "count: ", count($flags), "\n";

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
