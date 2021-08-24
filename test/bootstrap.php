<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');

$libDir = dirname(__DIR__);
$npMap  = [
    'Toolkit\PFlagTest\\' => $libDir . '/test/',
    'Toolkit\PFlag\\'     => $libDir . '/src/',
];

spl_autoload_register(static function ($class) use ($npMap): void {
    foreach ($npMap as $np => $dir) {
        $file = $dir . str_replace('\\', '/', substr($class, strlen($np))) . '.php';

        if (file_exists($file)) {
            include $file;
        }
    }
});

if (is_file(dirname(__DIR__, 3) . '/autoload.php')) {
    require dirname(__DIR__, 3) . '/autoload.php';
} elseif (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}
