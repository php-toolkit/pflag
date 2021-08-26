<?php declare(strict_types=1);

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Validator\NameValidator;
use Toolkit\PFlag\Validator\RegexValidator;

/**
 * class ValidatorTest
 */
class ValidatorTest extends BaseTestCase
{
    public function testRegexValidator(): void
    {
        $v = RegexValidator::new('^\w+$');
        $this->assertTrue($v('inhere', 'test'));

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage("flag 'test' value should match: ^\w+$");
        $v(' inhere ', 'test');
    }

    public function testNameValidator(): void
    {
        $v = NameValidator::new();
        $this->assertTrue($v('inhere', 'test'));

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage("flag 'test' value should match: " . NameValidator::DEFAULT_REGEX);
        $v(' inhere ', 'test');
    }
}
