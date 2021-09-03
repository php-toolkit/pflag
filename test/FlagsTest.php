<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Flags;
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use function edump;
use function vdump;

/**
 * Class FlagsTest
 *
 * @package Toolkit\PFlagTest\Flag
 */
class FlagsTest extends BaseTestCase
{
    public function testParse(): void
    {
        $fs = Flags::new();
        $fs->addOption(Option::new('name'));
        $fs->addOpt('age', '', 'age desc', FlagType::INT);
        $fs->addOpt('int1', '', 'opt1 desc', FlagType::INT, false, '89');

        $int1 = $fs->getDefinedOption('int1');
        $this->assertNotEmpty($int1);
        $this->assertSame(89, $int1->getDefault());
        $this->assertSame(89, $int1->getValue());

        self::assertTrue($fs->hasDefined('name'));
        self::assertFalse($fs->hasMatched('name'));

        $flags = ['--name', 'inhere', 'arg0', 'arg1'];
        $fs->parse($flags);

        self::assertTrue($fs->hasMatched('name'));
        $this->assertNotEmpty( $fs->getOption('name'));
        $this->assertSame('inhere', $fs->getOpt('name'));
        $this->assertSame(0, $fs->getOpt('age', 0));
        $this->assertSame(89, $fs->getOpt('int1'));
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());

        $fs->reset();
        $flags = ['--name', 'inhere', '-s', 'sv', '-f'];
        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: -s');
        $fs->parse($flags);
    }

    public function testStopOnFirstArg_false(): void
    {
        $fs = Flags::new();
        $this->assertTrue($fs->isStopOnFistArg());

        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);
        $flags = ['--name', 'inhere', '--age', '90', 'arg0', 'arg1'];
        // move an arg in middle
        $flags1 = ['--name', 'inhere', 'arg0', '--age', '90', 'arg1'];

        // $fs->parse($flags);
        // $this->assertCount(2, $fs->getRawArgs());
        // $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        // $fs->resetResults();
        //
        // // will stop parse on found 'arg0'
        // $fs->parse($flags1);
        // $this->assertCount(4, $fs->getRawArgs());
        // $this->assertSame(['arg0', '--age', '90', 'arg1'], $fs->getRawArgs());
        // $fs->resetResults();

        // set stopOnFirstArg=false
        $fs->setStopOnFistArg(false);
        $this->assertFalse($fs->isStopOnFistArg());

        vdump($flags);
        $fs->parse($flags);
        edump($fs);

        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $fs->resetResults();

        // will skip 'arg0' and continue parse '--age', '90'
        $fs->parse($flags1);
        vdump($fs);
        $this->assertCount(2, $fs->getRawArgs());
        $fs->reset();
    }
}
