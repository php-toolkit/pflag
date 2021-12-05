<?php declare(strict_types=1);

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\CliApp;
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
        $app->add('test1', fn() => $buf->set('key', 'in test1'));
        $app->addCommands([
            'test2' => [
                'desc'   => 'desc for test2 command',
                'handler' => fn() => $buf->set('key', 'in test2'),
                'options' => [
                    'opt1' => 'string;a string opt1 for command test2',
                    'opt2' => 'int;a int opt2 for command test2',
                ],
            ],
        ]);

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
