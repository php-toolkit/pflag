<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\FlagUtil;

class FlagUtilTest extends BaseFlagsTestCase
{
    public function testFilterOptionName(): void
    {
        $tests = [
            '-a'           => 'a',
            '-a=value'     => 'a=value',
            '--long'       => 'long',
            '--long=value' => 'long=value',
            // invalid
            '-'            => '',
            '- '           => '',
            '--'           => '',
            '--9'          => '',
            '--89'         => '',
            'arg0'         => '',
            'a89'          => '',
        ];
        foreach ($tests as $case => $want) {
            $this->assertSame($want, FlagUtil::filterOptionName($case));
        }

        $this->assertSame('', FlagUtil::filterOptionName('-9'));
        $this->assertSame('', FlagUtil::filterOptionName('89'));
    }
}
