<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\CliApp;
use Toolkit\PFlagTest\Cases\DemoCmdHandler;
use Toolkit\Stdlib\Obj\DataObject;

/**
 * class CliAppTest
 *
 * @author inhere
 */
class CliAppTest extends BaseFlagsTestCase
{
    private function initApp(CliApp $app): DataObject
    {
        $buf = DataObject::new();

        // php 7.4+
        $app->add('test1', fn () => $buf->set('key', 'in test1'));

        $app->addCommands([
            'test2' => [
                'desc'    => 'desc for test2 command',
                'handler' => function () use ($buf): void {
                    $buf->set('key', 'in test2');
                },
                'options' => [
                    'opt1' => 'string;a string opt1 for command test2',
                    'opt2' => 'int;a int opt2 for command test2',
                ],
            ],
        ]);

        $app->addHandler(DemoCmdHandler::class);

        return $buf;
    }

    public function testCliApp_basic(): void
    {
        $app = CliApp::global();
        $app->setScriptFile('/path/myapp');

        $this->assertEquals('/path/myapp', $app->getScriptFile());
        $this->assertEquals('myapp', $app->getBinName());
        $this->assertEquals('myapp', $app->getScriptName());
        $this->assertFalse($app->hasCommand('test1'));

        $buf = $this->initApp($app);

        $this->assertTrue($app->hasCommand('test1'));
        $this->assertTrue($app->hasCommand('test2'));
        $this->assertTrue($app->hasCommand('demo'));

        $app->runByArgs(['test1']);
        $this->assertEquals('in test1', $buf->get('key'));
    }

    public function testCliApp_showHelp(): void
    {
        $app = new CliApp();
        $this->initApp($app);

        $this->assertTrue($app->hasCommand('test1'));
        $this->assertTrue($app->hasCommand('test2'));

        $app->runByArgs(['-h']);
    }
}
