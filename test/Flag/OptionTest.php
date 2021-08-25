<?php declare(strict_types=1);

namespace Toolkit\PFlagTest\Flag;

use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlagTest\BaseTestCase;

/**
 * class OptionTest
 */
class OptionTest extends BaseTestCase
{
    public function testBasic(): void
    {
        $opt = Option::new('name');
        $this->assertSame(FlagType::STRING, $opt->getType());
        $this->assertFalse($opt->hasDefault());
        $this->assertFalse($opt->hasValue());

        $opt->setAlias('n1');
        $this->assertSame('n1', $opt->getAlias());

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
        $opt = Option::new('name');

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
    }
}
