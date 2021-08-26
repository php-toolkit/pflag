<?php declare(strict_types=1);

/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\PFlag\SFlags;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/sflags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
$flags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($flags);

$optRules = [
    // some option rules
    'name'  => 'string;;;this is an string option', // string
    'age'   => 'int;required;;this is an int option', // set required
    'tag,t' => 'strings;no;;array option, allow set multi times',
    'f'     => 'bool;no;;this is an bool option',
];
$argRules = [
    // some argument rules
    'string',
    // set name
    'arrArg' => 'strings;[a,b];;this is an array arg, allow multi value',
];

$fs = SFlags::new();
$fs->setScriptFile($scriptFile);

$fs->setOptRules($optRules);
$fs->setArgRules($argRules);

if (!$fs->parse($flags)) {
    return;
}

vdump(
// $fs->getRawArgs(),
    $fs->getOpts(),
    $fs->getArgs()
);

// vdump($fs->getArg('arrArg'));