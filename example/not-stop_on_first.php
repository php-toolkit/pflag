<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use Toolkit\PFlag\Flags;

require dirname(__DIR__) . '/test/bootstrap.php';

$fs = Flags::new();

$fs->addOptsByRules([
    'name' => 'string',
    'age'  => 'int',
]);
$flags = ['--name', 'inhere', '--age', '90', 'arg0', 'arg1'];

// set stopOnFirstArg=false
$fs->setStopOnFistArg(false);

$fs->parse($flags);
vdump($fs->toArray());

$fs->resetResults();

// move an arg in middle
$flags1 = ['--name', 'INHERE', 'arg0', '--age', '980', 'arg1'];

// will skip 'arg0' and continue parse '--age', '90'
$fs->parse($flags1);
vdump($fs->toArray());
