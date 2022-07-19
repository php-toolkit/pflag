<?php declare(strict_types=1);

/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\Cli\Cli;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\SFlags;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/sflags-demo.php -h
// php example/sflags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
$flags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($flags);

$optRules = [
    // some option rules
    'name'  => 'string;this is an string option', // string
    'age'   => 'int;this is an int option;required', // set required
    'tag,t' => 'strings;array option, allow set multi times',
    'f'     => 'bool;this is an bool option',
];
$argRules = [
    // some argument rules
    'string',
    // set name
    'arrArg' => 'strings;this is an array arg, allow multi value;;[a,b]',
];

$fs = SFlags::new();
$fs->setScriptFile($scriptFile);

$fs->setOptRules($optRules);
$fs->setArgRules($argRules);

$fs->setMoreHelp('more help message ...');

$fs->setExample([
    'example usage 1',
    'example usage 2',
]);

// do parsing
try {
    if (!$fs->parse($flags)) {
        // on render help
        return;
    }
} catch (Throwable $e) {
    if ($e instanceof FlagException) {
        Cli::colored('ERROR: ' . $e->getMessage(), 'error');
    } else {
        $code = $e->getCode() !== 0 ? $e->getCode() : -1;
        $eTpl = "Exception(%d): %s\nFile: %s(Line %d)\nTrace:\n%s\n";

        // print exception message
        printf($eTpl, $code, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }

    return;
}

vdump(
// $fs->getRawArgs(),
    $fs->getOpts(),
    $fs->getArgs()
);

// vdump($fs->getArg('arrArg'));
