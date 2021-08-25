<?php declare(strict_types=1);

namespace Toolkit\PFlagTest\Flag;

use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlagTest\BaseTestCase;

/**
 * class ArgumentTest
 */
class ArgumentTest extends BaseTestCase
{
    public function testBasic(): void
    {
        $arg = Argument::new('name');
        $this->assertSame(FlagType::STRING, $arg->getType());
        $this->assertFalse($arg->hasDefault());

        $arg->setDefault(89);
        $this->assertSame(89, $arg->getDefault());
        $this->assertTrue($arg->hasDefault());
        $this->assertFalse($arg->hasValue());
        $this->assertNull($arg->getValue());

        $arg->init();
        $this->assertSame('89', $arg->getValue());
        $this->assertTrue($arg->hasValue());
        $this->assertSame('89', $arg->getDefault());

    }
}
