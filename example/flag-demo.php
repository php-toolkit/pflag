<?php declare(strict_types=1);

/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\PFlag\Flags;
use Toolkit\PFlag\FlagType;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/sflags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
$flags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($flags);

$fs = Flags::new();
$fs->setScriptFile($scriptFile);

// add options
$fs->addOpt('age', 'a', 'this is a int option', FlagType::INT);
$fs->addOptByRule('name,n', 'string;true;;this is a string option');
$fs->addOptsByRules([
    'tag,t' => 'strings;no;;array option, allow set multi times',
    'f'     => 'bool;no;;this is an bool option',
]);

// add arguments
$fs->addArg('strArg', 'the first arg, is string', 'string', true);
$fs->addArg('arrArg', 'the second arg, is array', 'strings');

// edump($fs);
if (!$fs->parse($flags)) {
    return;
}

vdump(
    $fs->getOpts(),
    $fs->getArgs()
);
