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
        self::assertTrue($fs->hasDefined('name'));
        self::assertFalse($fs->hasMatched('name'));

        $args = ['--name', 'inhere', 'arg0', 'arg1'];
        $fs->parse($args);
        self::assertTrue($fs->hasMatched('name'));

        $fs->reset();
        $args = ['--name', 'inhere', '-s', 'sv', '-f'];
        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: -s');
        $fs->parse($args);
    }
}
