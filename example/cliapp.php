<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\Cli\Cli;
use Toolkit\PFlag\CliApp;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlagTest\Cases\DemoCmdHandler;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/cliapp.php
// php example/cliapp.php test2 -h
$cli = CliApp::newWith(static function (CliApp $app): void {
    $app->setName('myApp');
    $app->setDesc('my cli application. v1.0.1');
})
    ->add('test1', fn (FlagsParser $fs) => vdump($fs->getOpts()), [
        'desc'    => 'the test 1 command',
        'options' => [
            'opt1' => 'opt1 for command test1',
            'opt2' => 'int;opt2 for command test1',
        ],
    ]);

$cli->add('test2', function (FlagsParser $fs): void {
    Cli::info('options:');
    vdump($fs->getOpts());
    Cli::info('arguments:');
    vdump($fs->getArgs());
}, [
    // 'desc'    => 'the test2 command',
    'options'   => [
        'opt1' => 'string;a string opt1 for command test2, and is required;true',
        'opt2' => 'int;a int opt2 for command test2',
    ],
    'arguments' => [
        'arg1' => 'required arg1 for command test2;true',
    ]
]);

$cli->add('show-err', fn () => throw new RuntimeException('test show exception'));

$cli->addHandler(DemoCmdHandler::class);

$cli->run();
