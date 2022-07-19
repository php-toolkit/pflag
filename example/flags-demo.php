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
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\Flags;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\Validator\EnumValidator;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/flags-demo.php -h
// php example/flags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
$flags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($flags);

$fs = Flags::new();
$fs->setScriptFile($scriptFile);
/** @see Flags::$settings */
$fs->setSettings([
    'descNlOnOptLen' => 26
]);

// add options
// - quick add
$fs->addOpt('age', 'a', 'this is a int option', FlagType::INT);

// - use string rule
$fs->addOptByRule('name,n', 'string;this is a string option;true;');
// -- add multi option at once.
$fs->addOptsByRules([
    'tag,t' => 'strings;array option, allow set multi times',
    'f'     => 'bool;this is an bool option',
]);
// - use array rule
/** @see Flags::DEFINE_ITEM for array rule */
$fs->addOptByRule('name-is-very-lang', [
    'type'   => FlagType::STRING,
    'desc'   => 'option name is to lang, desc will print on newline',
    'shorts' => ['d','e'],
    'alias'  => 'nv',
    // TIP: add validator limit input value.
    'validator' => EnumValidator::new(['one', 'two', 'three']),
]);

// - use Option
$opt = Option::new('str1', "this is string option, \ndesc has multi line, \nhaha...");
$opt->setDefault('defVal');
$fs->addOption($opt);

// add arguments
// - quick add
$fs->addArg('strArg1', 'the is string arg and is required', 'string', true);
// - use string rule
$fs->addArgByRule('intArg2', 'int;this is a int arg and with default value;no;89');
// - use Argument object
$arg = Argument::new('arrArg');
// OR $arg->setType(FlagType::ARRAY);
$arg->setType(FlagType::STRINGS);
$arg->setDesc("this is an array arg,\n allow multi value,\n must define at last");
$fs->addArgument($arg);

$fs->setMoreHelp('more help message ...');

$fs->setExampleHelp([
    'example usage 1',
    'example usage 2',
]);

// edump($fs);

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

vdump($fs->getOpts(), $fs->getArgs());
