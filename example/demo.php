<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

use PhpComLab\CliMarkdown\CliMarkdown;

require dirname(__DIR__) . '/vendor/autoload.php';

$contents = file_get_contents(dirname(__DIR__) . '/README.md');
$praser = new CliMarkdown;

$rendered = $praser->render($contents);

echo $rendered;
