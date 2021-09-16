<?php declare(strict_types=1);

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagsParser;
use function get_class;

/**
 * class CommonTest
 */
class FlagsParserTest extends BaseFlagsTestCase
{
    public function testStopOnFirstArg(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs) {
            $this->runStopOnFirstArg($fs);
        });
    }

    private function runStopOnFirstArg(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);
        $flags = ['--name', 'inhere', '--age', '90', 'arg0', 'arg1'];
        // move an arg in middle
        $flags1 = ['--name', 'inhere', 'arg0', '--age', '90', 'arg1'];

        // ----- stopOnFirstArg=true
        $this->assertTrue($fs->isStopOnFistArg());

        $fs->parse($flags);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->resetResults();

        // will stop parse on found 'arg0'
        $fs->parse($flags1);
        $this->assertCount(4, $fs->getRawArgs());
        $this->assertSame(['arg0', '--age', '90', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere'], $fs->getOpts());
        $fs->resetResults();

        // ----- set stopOnFirstArg=false
        $fs->setStopOnFistArg(false);
        $this->assertFalse($fs->isStopOnFistArg());

        $fs->parse($flags);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->resetResults();

        // will skip 'arg0' and continue parse '--age', '90'
        $fs->parse($flags1);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->reset();
    }

    public function testSkipOnUndefined(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs) {
            $this->runSkipOnUndefined_false($fs);
        });

        $this->runTestsWithParsers(function (FlagsParser $fs) {
            $this->runSkipOnUndefined_true($fs);
        });
    }

    private function runSkipOnUndefined_false(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);

        // ----- skipOnUndefined=false
        $this->assertFalse($fs->isSkipOnUndefined());

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: --not-exist');
        $flags = ['--name', 'inhere', '--not-exist', '--age', '90', 'arg0', 'arg1'];
        $fs->parse($flags);
    }

    /**
     * @param FlagsParser $fs
     */
    private function runSkipOnUndefined_true(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);

        // ----- skipOnUndefined=true
        $fs->setSkipOnUndefined(true);
        $this->assertTrue($fs->isSkipOnUndefined());

        $flags = ['--name', 'inhere', '--not-exist', '--age', '90', 'arg0', 'arg1'];
        $fs->parse($flags);
        // vdump($fs->toArray());
        $this->assertCount(3, $fs->getRawArgs());
        $this->assertSame(['--not-exist', 'arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
    }

    public function testException_RepeatName(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doCheckRepeatName($fs);
        }
    }

    protected function doCheckRepeatName(FlagsParser $fs): void
    {
        $e = $this->runAndGetException(function (FlagsParser $fs) {
            $fs->addOptsByRules([
                '--name' => 'an string',
                'name'   => 'an string',
            ]);
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));

        $e = $this->runAndGetException(function (FlagsParser $fs) {
            $fs->addArgsByRules([
                'name' => 'an string',
            ]);
            $fs->addArg('name', 'an string');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
    }

    public function testRenderHelp_showTypeOnHelp(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs) {
            $this->bindingOptsAndArgs($fs);
            $this->renderFlagsHelp($fs);
        });
    }

    public function testRenderHelp_showTypeOnHelp_false(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs) {
            $fs->setShowTypeOnHelp(false);
            $this->bindingOptsAndArgs($fs);
            $this->renderFlagsHelp($fs);
        });
    }

    private function renderFlagsHelp(FlagsParser $fs): void
    {
        $ok = $fs->parse(['-h']);
        $this->assertFalse($ok);
        $this->assertSame(FlagsParser::STATUS_HELP, $fs->getParseStatus());
    }


    public function testException_addOpt(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doCheckErrorOnAddOpt($fs);
        }
    }

    private function doCheckErrorOnAddOpt(FlagsParser $fs): void
    {
        // empty name
        $e = $this->runAndGetException(function (FlagsParser $fs) {
            $fs->addOpt('', '', 'an desc');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame('invalid flag option name: ', $e->getMessage());

        // invalid name
        $e = $this->runAndGetException(function (FlagsParser $fs) {
            $fs->addOpt('name=+', '', 'an desc');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame('invalid flag option name: name=+', $e->getMessage());
    }
}
