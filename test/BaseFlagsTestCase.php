<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Toolkit\PFlag\Flags;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\SFlags;
use Toolkit\Stdlib\Php;
use function in_array;

/**
 * Class BaseTestCase
 *
 * @package Inhere\ConsoleTest
 */
abstract class BaseFlagsTestCase extends TestCase
{
    /**
     * @param callable $cb
     * @param mixed ...$args
     *
     * @return Throwable
     */
    protected function runAndGetException(callable $cb, ...$args): Throwable
    {
        try {
            $cb(...$args);
        } catch (Throwable $e) {
            return $e;
        }

        return new RuntimeException('NO ERROR');
    }

    public function assertArrayHasValue($needle, array $arr, string $message = ''): void
    {
        $has = in_array($needle, $arr, true);
        $msg = 'The array ' . Php::toString($arr) . ' should contains: ' .  $needle;

        $this->assertTrue($has, $message ?: $msg);
    }

    /**
     * @param Closure $testFunc
     */
    protected function runTestsWithParsers(Closure $testFunc): void
    {
        echo '- tests by use the parser: ', Flags::class, "\n";
        $fs = Flags::new(['name' => 'flags']);
        $testFunc($fs);

        echo '- tests by use the parser: ', SFlags::class, "\n";
        $sfs = SFlags::new(['name' => 'simple-flags']);
        $testFunc($sfs);
    }

    protected function createParsers(): array
    {
        $fs  = Flags::new(['name' => 'flags']);
        $sfs = SFlags::new(['name' => 'simple-flags']);

        return [$fs, $sfs];
        // return [$sfs];
    }

    protected function bindingOptsAndArgs(FlagsParser $fs): void
    {
        $optRules = [
            'int-opt'         => 'int;an int option',
            'int-opt1'        => 'int;an int option with shorts;false;;i,g',
            'str-opt'         => 'an string option',
            'str-opt1'        => "string;an int option with required,\nand has multi line desc;true",
            'str-opt2'        => 'string;an string option with default;false;inhere',
            'bool-opt'        => 'bool;an int option with an short;false;;b',
            '-a, --bool-opt1' => 'bool;an int option with an short',
            's'               => 'string;an string option only short name',
        ];
        $argRules = [
            'int-arg' => 'int;an int argument',
            'str-arg' => "an string argument,\nand has multi line desc",
        ];

        $fs->addOptsByRules($optRules);
        $fs->addArgsByRules($argRules);
    }
}
