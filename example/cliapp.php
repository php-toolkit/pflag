<?php

use Toolkit\Cli\Cli;
use Toolkit\PFlag\CliApp;
use Toolkit\PFlag\FlagsParser;

require dirname(__DIR__) . '/test/bootstrap.php';

// run demo:
// php example/cliapp.php

$app = new CliApp();

$app->add('test1', fn(FlagsParser $fs) => vdump($fs->getOpts()), [
    'desc'    => 'the test 1 command',
    'options' => [
        'opt1' => 'opt1 for command test1',
        'opt2' => 'int;opt2 for command test1',
    ],
]);

$app->add('test2', function (FlagsParser $fs) {
    Cli::info('options:');
    vdump($fs->getOpts());
    Cli::info('arguments:');
    vdump($fs->getArgs());
}, [
    // 'desc'    => 'the test2 command',
    'options' => [
        'opt1' => 'string;a string opt1 for command test2, and is required;true',
        'opt2' => 'int;a int opt2 for command test2',
    ],
    'arguments' => [
        'arg1' => 'required arg1 for command test2;true',
    ]
]);

$app->add('show-err', fn() => throw new RuntimeException('test show exception'));

$app->run();

