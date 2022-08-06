<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\Cli\Cli;
use Toolkit\PFlag\CliCmd;
use Toolkit\PFlag\FlagsParser;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/clicmd.php -h
// php example/clicmd.php --name inhere value1
// php example/clicmd.php --age 23 --name inhere value1

CliCmd::newWith(static function (CliCmd $cmd): void {
    $cmd->name = 'demo';
    $cmd->desc = 'description for demo command';

    // config flags
    $cmd->options = [
        'age, a'  => 'int;the option age, is int',
        'name, n' => 'the option name, is string and required;true',
        'tags, t' => 'array;the option tags, is array',
    ];

    // or use property
    // $cmd->arguments = [...];
    // $cmd->getFlags()->setExample($example);
})
    ->withArguments([
        'arg1' => 'this is arg1, is string'
    ])
    ->setHandler(function (FlagsParser $fs): void {
        Cli::info('options:');
        vdump($fs->getOpts());
        Cli::info('arguments:');
        vdump($fs->getArgs());
    })
    ->run();
