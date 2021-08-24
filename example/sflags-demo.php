<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\SFlags;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/sflags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
$rawFlags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($rawFlags);

$optRules = [
    // some option rules
    'name', // string
    'age'    => 'int,required', // set required
    'tag,t' => FlagType::ARRAY,
    'f'      => FlagType::BOOL,
];
$argRules = [
    // some argument rules
    'string',
    'array',
];

$fs = SFlags::new();
// $fs = $fs->parseDefined($rawFlags, $optRules, $argRules);

$fs->setOptRules($optRules);
$fs->setArgRules($argRules);
$fs->parse($rawFlags);

vdump(
    // $fs->getRawArgs(),
    $fs->getArgs(),
    $fs->getOpts()
);
