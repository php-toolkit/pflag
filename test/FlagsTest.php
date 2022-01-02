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

/**
 * Class FlagsTest
 *
 * @package Toolkit\PFlagTest\Flag
 */
class FlagsTest extends BaseFlagsTestCase
{
    public function testAddOptionAndParse(): void
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
        $this->assertNotEmpty($fs->getOption('name'));
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
}
