<?php
/**
 * Created by PhpStorm.
 * - the is a config file for compile phar package.
 *
 * User: Inhere
 * Date: 2018/1/26 22:11
 */

use Inhere\Console\Component\PharCompiler;

// config
/** @var PharCompiler $compiler */
$compiler
  // ->stripComments(false)
  ->setShebang(true)
  ->addExclude([
    'test',
    'runtime',
    'resource',
  ])
  ->addFile([
    'LICENSE',
    'composer.json',
    'README.md',
  ])
  ->setCliIndex('bin/kite')
  // ->setWebIndex('web/index.php')
  // ->setVersionFile('config/config.php')
;

$compiler->on(PharCompiler::ADD_CLI_INDEX, static function ($file, $content) {
    echo "Set publish date to {$file}\n";

    $pubDate = date('Y.m.d H:i');

    return str_replace("date('Y.m.d')", "'{$pubDate}'", $content);
});
