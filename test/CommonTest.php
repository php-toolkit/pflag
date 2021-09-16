<?php declare(strict_types=1);

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Flags;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\SFlags;
use function get_class;

/**
 * class CommonTest
 */
class CommonTest extends BaseTestCase
{
    public function testStopOnFirstArg(): void
    {
        $fs = Flags::new();
        $this->runStopOnFirstArg($fs);

        $sfs = SFlags::new();
        $this->runStopOnFirstArg($sfs);
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

    public function testSkipOnUndefined_false(): void
    {
        $fs = Flags::new();
        $this->runSkipOnUndefined_false($fs);

        $sfs = SFlags::new();
        $this->runSkipOnUndefined_false($sfs);
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

    public function testSkipOnUndefined_true(): void
    {
        $fs = Flags::new();
        $this->runSkipOnUndefined_true($fs);

        $sfs = SFlags::new();
        $this->runSkipOnUndefined_true($sfs);
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

    public function testParseRule_string(): void
    {
        $p = RuleParser::new();

        $define = $p->parseOpt('string;flag desc;true;inhere;a,b', 'username');

        $this->assertNotEmpty($define);
        $this->assertSame('string', $define['type']);
        $this->assertSame('username', $define['name']);
        $this->assertSame('flag desc', $define['desc']);
        $this->assertSame('inhere', $define['default']);
        $this->assertSame(['a', 'b'], $define['shorts']);
        $this->assertTrue($define['required']);

        $define = $p->parseOpt('strings;this is an array, allow multi value;;[ab,cd]', 'names');
        $this->assertFalse($define['required']);
        $this->assertEmpty($define['shorts']);
        $this->assertSame(['ab', 'cd'], $define['default']);

        $define = $p->parseOpt('ints;this is an array, allow multi value;no;[23,45];', 'ids');
        $this->assertFalse($define['required']);
        $this->assertEmpty($define['shorts']);
        $this->assertSame([23, 45], $define['default']);

        $define = $p->parseOpt('array;this is an array, allow multi value;no;[23,45];', 'ids');
        $this->assertFalse($define['required']);
        $this->assertEmpty($define['shorts']);
        $this->assertSame(['23', '45'], $define['default']);
    }

    protected function createParsers(): array
    {
        $fs = Flags::new(['name' => 'flags']);
        $sfs = SFlags::new(['name' => 'simple-flags']);

        return [$fs, $sfs];
        // return [$sfs];
    }

    public function testRepeatName(): void
    {
        echo "- testRepeatName\n";
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

    public function testRenderHelp(): void
    {
        echo "- testRenderHelp\n";
        $fs = Flags::new(['name' => 'flags']);
        $this->bindingOptsAndArgs($fs);
        $this->runRenderHelp($fs);

        $sfs = SFlags::new(['name' => 'simple-flags']);
        $this->bindingOptsAndArgs($sfs);
        $this->runRenderHelp($sfs);
    }

    public function testRenderHelp_showTypeOnHelp_false(): void
    {
        echo "- testRenderHelp_showTypeOnHelp_false \n";
        $fs = Flags::new(['name' => 'flags']);
        $fs->setShowTypeOnHelp(false);
        $this->bindingOptsAndArgs($fs);
        $this->runRenderHelp($fs);

        $sfs = SFlags::new(['name' => 'simple-flags']);
        $sfs->setShowTypeOnHelp(false);
        $this->bindingOptsAndArgs($sfs);
        $this->runRenderHelp($sfs);
    }

    protected function bindingOptsAndArgs(FlagsParser $fs): void
    {
        $optRules = [
            'intopt'  => 'int;an int option',
            'intopt1' => 'int;an int option with shorts;false;;i,g',
        ];
        $argRules = [
            'intarg' => 'int;an int argument',
        ];

        $fs->addOptsByRules($optRules);
        $fs->addArgsByRules($argRules);
    }

    public function runRenderHelp(FlagsParser $fs): void
    {
        $ok = $fs->parse(['-h']);
        $this->assertFalse($ok);
        $this->assertSame(FlagsParser::STATUS_HELP, $fs->getParseStatus());
    }
}
