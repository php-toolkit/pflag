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
class FlagsTest extends BaseTestCase
{
    public function testParse(): void
    {
        $fs = Flags::new();
        $fs->addOption(Option::new('name'));
        $fs->addOpt('age', '', 'age desc', FlagType::INT);
        $fs->addOpt('int1', '', 'opt1 desc', FlagType::INT, false, '89');
        // vdump($fs->getDefinedOption('int1'));

        self::assertTrue($fs->hasDefined('name'));
        self::assertFalse($fs->hasMatched('name'));

        $args = ['--name', 'inhere', 'arg0', 'arg1'];
        $fs->parse($args);

        self::assertTrue($fs->hasMatched('name'));
        $this->assertNotEmpty( $fs->getOption('name'));
        $this->assertSame('inhere', $fs->getOpt('name'));
        $this->assertSame(0, $fs->getOpt('age', 0));
        $this->assertSame(89, $fs->getOpt('int1'));

        $fs->reset();
        $args = ['--name', 'inhere', '-s', 'sv', '-f'];
        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: -s');
        $fs->parse($args);
    }
}
