<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest\Flag;

use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlagTest\BaseFlagsTestCase;

/**
 * class OptionTest
 */
class OptionTest extends BaseFlagsTestCase
{
    public function testBasic(): void
    {
        $opt = Option::new('name');
        $this->assertSame(FlagType::STRING, $opt->getType());
        $this->assertFalse($opt->hasDefault());
        $this->assertFalse($opt->hasValue());

        $opt->setAliases(['n1', 'n2']);
        $this->assertSame(['n1', 'n2'], $opt->getAliases());
        $this->assertSame('--n1, --n2, --name', $opt->getHelpName());

        $opt->setShortcut('n');
        $this->assertEquals('-n, --n1, --n2, --name', $opt->getHelpName());

        $opt->setDefault(89);
        $this->assertTrue($opt->hasDefault());
        $this->assertFalse($opt->hasValue());
        $this->assertNull($opt->getValue());
        $this->assertSame(89, $opt->getDefault());

        $opt->init();
        $this->assertSame('89', $opt->getValue());
        $this->assertTrue($opt->hasValue());
        $this->assertSame('89', $opt->getDefault());
    }

    public function testShortcut(): void
    {
        $opt = Option::newByArray('name', [
            'desc' => 'option name',
        ]);

        $tests = [
            'a,b',
            '-a,b',
            '-a,-b',
            '-a, -b',
        ];
        foreach ($tests as $test) {
            $opt->setShortcut($test);
            $this->assertSame(['a', 'b'], $opt->getShorts());
            $this->assertSame('-a, -b', $opt->getShortcut());
        }

        $this->assertSame('-a, -b, --name', $opt->getHelpName());
    }
}
