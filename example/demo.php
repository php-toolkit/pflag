<?php

use PhpComLab\CliMarkdown\CliMarkdown;

require dirname(__DIR__) . '/vendor/autoload.php';

$contents = file_get_contents(dirname(__DIR__) . '/README.md');
$praser = new CliMarkdown;

$rendered = $praser->render($contents);

echo $rendered;

