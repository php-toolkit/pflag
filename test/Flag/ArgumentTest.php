<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest\Flag;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\Validator\EmptyValidator;
use Toolkit\PFlagTest\BaseFlagsTestCase;

/**
 * class ArgumentTest
 */
class ArgumentTest extends BaseFlagsTestCase
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

    public function testValidate(): void
    {
        $arg = Argument::new('name');
        $arg->setValidator(EmptyValidator::new());

        $arg->setValue('inhere');
        $this->assertSame('inhere', $arg->getValue());

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage("flag 'name' value cannot be empty");
        $arg->setValue('');
    }
}
