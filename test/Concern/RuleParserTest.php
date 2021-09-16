<?php declare(strict_types=1);

namespace Toolkit\PFlagTest\Concern;

use Toolkit\PFlagTest\BaseFlagsTestCase;
use Toolkit\PFlagTest\RuleParser;

/**
 * class RuleParserTest
 */
class RuleParserTest extends BaseFlagsTestCase
{
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

}
