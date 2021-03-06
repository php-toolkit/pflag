<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest;

use InvalidArgumentException;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\Validator\EnumValidator;
use function get_class;

/**
 * class CommonTest
 */
class FlagsParserTest extends BaseFlagsTestCase
{
    public function testParserBasic(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->doCheckBasic($fs);
        });
    }

    private function doCheckBasic(FlagsParser $fs): void
    {
        $this->assertTrue($fs->isEmpty());
        $this->assertFalse($fs->isNotEmpty());
        $this->assertFalse($fs->hasShortOpts());
        $this->assertFalse($fs->hasArg('github'));

        $fs->setArgRules([
            'github' => 'an string argument'
        ]);
        $this->assertFalse($fs->isEmpty());
        $this->assertTrue($fs->hasArg('github'));
        $this->assertFalse($fs->hasArg('not-exist'));
        $this->assertTrue($fs->isNotEmpty());
        $this->assertFalse($fs->hasShortOpts());
        $this->assertNotEmpty($fs->getArgDefine('github'));

        $fs->setOptRules([
            '-n,--name' => 'an string option'
        ]);
        $this->assertFalse($fs->isEmpty());
        $this->assertTrue($fs->isNotEmpty());
        $this->assertTrue($fs->hasShortOpts());
        $this->assertTrue($fs->hasOpt('name'));
        $this->assertFalse($fs->hasInputOpt('name'));
        $this->assertFalse($fs->hasOpt('not-exist'));
        $this->assertNotEmpty($fs->getOptDefine('name'));

        $fs->parseCmdline('bin/app --name inhere http://github.com/inhere');
        $this->assertSame('bin/app', $fs->getScriptFile());
        $this->assertSame('app', $fs->getScriptName());
        $this->assertSame('inhere', $fs->getOpt('name'));
        $this->assertSame('http://github.com/inhere', $fs->getArg('github'));
    }

    // public function testOption_aliases(): void
    // {
    //     $this->runTestsWithParsers(function (FlagsParser $fs) {
    //         // $fs->addOptByRule('', $rule)
    //     });
    // }

    public function testGetOptAndGetArg(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->bindingOptsAndArgs($fs);
            $this->doTestGetOptAndGetArg($fs);
        });
    }

    private function doTestGetOptAndGetArg(FlagsParser $fs): void
    {
        // int type
        $ok = $fs->parse(['--str-opt1', 'val1', '--int-opt', '335', '233']);
        $this->assertTrue($ok);
        $this->assertSame(335, $fs->getOpt('int-opt'));
        $this->assertSame(335, $fs->getMustOpt('int-opt'));
        $this->assertSame(233, $fs->getArg('int-arg'));
        $this->assertSame(233, $fs->getMustArg('int-arg'));
        $fs->resetResults();

        // getMustOpt
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->getMustOpt('str-opt');
        }, $fs);

        $this->assertSame(InvalidArgumentException::class, get_class($e));
        $this->assertSame("The option 'str-opt' is required", $e->getMessage());

        // getMustArg
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->getMustArg('str-arg');
        }, $fs);

        $this->assertSame(InvalidArgumentException::class, get_class($e));
        $this->assertSame("The argument '#1(str-arg)' is required", $e->getMessage());
    }

    public function testParse_specialArg(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $fs->addOpt('a', '', 'an string opt');
            $fs->addArg('num', 'an int arg', 'int');

            $ok = $fs->parse(['-a', 'val0', '-9']);
            $this->assertTrue($ok);
            $this->assertSame([-9], $fs->getArgs());
            $fs->resetResults();

            $ok = $fs->parse(['-a', 'val0', '-90']);
            $this->assertTrue($ok);
            $this->assertSame([-90], $fs->getArgs());
            $fs->resetResults();
        });
    }

    public function testStopOnTwoHl(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->doCheckStopOnTwoHl($fs);
        });
    }

    private function doCheckStopOnTwoHl(FlagsParser $fs): void
    {
        $fs->addOpt('name', '', 'desc');
        $fs->addArg('arg0', 'desc');
        $this->assertFalse($fs->isStrictMatchArgs());

        $ok = $fs->parse(['--name', 'inhere', 'val0']);
        $this->assertTrue($ok);
        $this->assertSame('val0', $fs->getArg('arg0'));
        $fs->resetResults();

        $ok = $fs->parse(['--name', 'inhere', '--', '--val0']);
        $this->assertTrue($ok);
        $this->assertSame('--val0', $fs->getArg('arg0'));
        $fs->resetResults();

        $ok = $fs->parse(["--", "-e", "dev", "-v", "port=3455"]);
        $this->assertTrue($ok);
        $this->assertNotEmpty($fs->getArgs());
        $this->assertSame('-e', $fs->getArg('arg0'));

        $otherArgs = $fs->getRemainArgs();
        $this->assertArrayHasValue('port=3455', $otherArgs);
        $fs->resetResults();
    }

    public function testStopOnFirstArg(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->runStopOnFirstArg($fs);
        });
    }

    private function runStopOnFirstArg(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);
        $flags = ['--name', 'inhere', '--age', '90', 'arg0', 'arg1'];
        // move an arg in middle
        $flags1 = ['--name', 'inhere', 'arg0', '--age', '90', 'arg1'];

        // ----- stopOnFirstArg=true
        $this->assertTrue($fs->isStopOnFistArg());

        $fs->parse($flags);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->resetResults();

        // will stop parse on found 'arg0'
        $fs->parse($flags1);
        $this->assertCount(4, $fs->getRawArgs());
        $this->assertSame(['arg0', '--age', '90', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere'], $fs->getOpts());
        $fs->resetResults();

        // ----- set stopOnFirstArg=false
        $fs->setStopOnFistArg(false);
        $this->assertFalse($fs->isStopOnFistArg());

        $fs->parse($flags);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->resetResults();

        // will skip 'arg0' and continue parse '--age', '90'
        $fs->parse($flags1);
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame(['arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
        $fs->reset();
    }

    public function testSkipOnUndefined(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->runSkipOnUndefined_false($fs);
        });

        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->runSkipOnUndefined_true($fs);
        });
    }

    private function runSkipOnUndefined_false(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);

        // ----- skipOnUndefined=false
        $this->assertFalse($fs->isSkipOnUndefined());

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: --not-exist');
        $flags = ['--name', 'inhere', '--not-exist', '--age', '90', 'arg0', 'arg1'];
        $fs->parse($flags);
    }

    /**
     * @param FlagsParser $fs
     */
    private function runSkipOnUndefined_true(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'name' => 'string',
            'age'  => 'int',
        ]);

        // ----- skipOnUndefined=true
        $fs->setSkipOnUndefined(true);
        $this->assertTrue($fs->isSkipOnUndefined());

        $flags = ['--name', 'inhere', '--not-exist', '--age', '90', 'arg0', 'arg1'];
        $fs->parse($flags);
        // vdump($fs->toArray());
        $this->assertCount(3, $fs->getRawArgs());
        $this->assertSame(['--not-exist', 'arg0', 'arg1'], $fs->getRawArgs());
        $this->assertSame(['name' => 'inhere', 'age' => 90], $fs->getOpts());
    }

    public function testRenderHelp_showTypeOnHelp(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $this->bindingOptsAndArgs($fs);
            $this->renderFlagsHelp($fs);
        });
    }

    public function testRenderHelp_showTypeOnHelp_false(): void
    {
        $this->runTestsWithParsers(function (FlagsParser $fs): void {
            $fs->setShowTypeOnHelp(false);
            $this->bindingOptsAndArgs($fs);
            $this->renderFlagsHelp($fs);
        });
    }

    private function renderFlagsHelp(FlagsParser $fs): void
    {
        $ok = $fs->parse(['-h']);
        $this->assertFalse($ok);
        $this->assertSame(FlagsParser::STATUS_HELP, $fs->getParseStatus());
    }

    public function testException_RepeatName(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doCheckRepeatName($fs);
        }
    }

    protected function doCheckRepeatName(FlagsParser $fs): void
    {
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addOptsByRules([
                '--name' => 'an string',
                'name'   => 'an string',
            ]);
        }, $fs);

        $this->assertEquals(FlagException::class, get_class($e));

        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addArgsByRules([
                'name' => 'an string',
            ]);
            $fs->addArg('name', 'an string');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
    }

    public function testException_addOpt(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doCheckErrorOnAddOpt($fs);
        }
    }

    private function doCheckErrorOnAddOpt(FlagsParser $fs): void
    {
        // empty name
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addOpt('', '', 'an desc');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame('invalid flag option name: ', $e->getMessage());

        // invalid name
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addOpt('name=+', '', 'an desc');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame('invalid flag option name: name=+', $e->getMessage());

        // invalid type
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addOpt('name', '', 'an desc', 'invalid');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame("invalid flag type 'invalid', option: name", $e->getMessage());
    }

    public function testException_addArg(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doCheckErrorOnAddArg($fs);
        }
    }

    private function doCheckErrorOnAddArg(FlagsParser $fs): void
    {
        // invalid name
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addArg('name=+', 'an desc');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame('invalid flag argument name: #0(name=+)', $e->getMessage());

        // invalid type
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->addArg('name', 'an desc', 'invalid');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame("invalid flag type 'invalid', argument: #0(name)", $e->getMessage());
    }

    public function testSetOptAndSetArg(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->bindingOptsAndArgs($fs);
            $this->doCheckSetOptAndSetArg($fs);
        }
    }

    private function doCheckSetOptAndSetArg($fs): void
    {
        $this->assertSame(0, $fs->getOpt('int-opt'));
        $this->assertSame('', $fs->getOpt('str-opt'));
        $this->assertSame('', $fs->getArg('str-arg'));

        // test set
        $fs->setOpt('int-opt', '22');
        $fs->setOpt('str-opt', 'value');
        $fs->setArg('str-arg', 'value1');

        $this->assertSame(22, $fs->getOpt('int-opt'));
        $this->assertSame('value', $fs->getOpt('str-opt'));
        $this->assertSame('value1', $fs->getArg('str-arg'));

        // test set trust
        $fs->setTrustedOpt('int-opt', '33'); // will not format, validate value.
        $fs->setTrustedOpt('str-opt', 'trust-value');
        $fs->setTrustedArg('str-arg', 'trust-value1');

        $this->assertSame('33', $fs->getOpt('int-opt'));
        $this->assertSame('trust-value', $fs->getOpt('str-opt'));
        $this->assertSame('trust-value1', $fs->getArg('str-arg'));

        // test error
        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->setOpt('not-exist-opt', '22');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame("flag option 'not-exist-opt' is undefined", $e->getMessage());

        $e = $this->runAndGetException(function (FlagsParser $fs): void {
            $fs->setArg('not-exist-arg', '22');
        }, $fs);

        $this->assertSame(FlagException::class, get_class($e));
        $this->assertSame("flag argument 'not-exist-arg' is undefined", $e->getMessage());
    }

    public function testParse_arrayArg(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doTestParse_arrayArg($fs);
        }
    }

    public function doTestParse_arrayArg(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'env, e'     => [
                'type'     => FlagType::STRING,
                'required' => true,
            ],
        ]);
        $fs->addArgByRule('files', 'array;a array arg');

        $flags = ['-e', 'dev', 'abc'];
        $fs->parse($flags);

        $this->assertNotEmpty($fs->getOpts());
        $this->assertNotEmpty($fs->getArgs());
        $this->assertEquals(['abc'], $fs->getArg('files'));
        $fs->resetResults();

        $flags = ['-e', 'dev', 'abc', 'def'];
        $fs->parse($flags);

        $this->assertNotEmpty($fs->getOpts());
        $this->assertNotEmpty($fs->getArgs());
        $this->assertEquals(['abc', 'def'], $fs->getArg('files'));
        $fs->resetResults();
    }

    public function testParse_optValueIsKV(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doTestParse_optValueIsKV($fs);
        }
    }

    public function doTestParse_optValueIsKV(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'env, e'     => [
                'type'     => FlagType::STRING,
                'required' => true,
            ],
            'vars,var,v' => [
                'type' => FlagType::ARRAY,
                'desc' => 'can append some extra vars, format: KEY=VALUE',
            ],
        ]);

        $flags = ['-e', 'dev', '--var', 'key0=val0', '-v', 'port=3445'];
        $fs->parse($flags);

        $this->assertNotEmpty($fs->getOpts());
        $this->assertNotEmpty($fs->getInfo(true));
        $this->assertEquals('vars', $fs->resolveAlias('v'));
        $this->assertEquals('vars', $fs->resolveAlias('var'));
        $this->assertEquals(['key0=val0', 'port=3445'], $fs->getOpt('vars'));
    }

    public function testRenderHelp_withValidator(): void
    {
        foreach ($this->createParsers() as $fs) {
            $this->doTestRenderHelp_withValidator($fs);
        }
    }

    public function doTestRenderHelp_withValidator(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'env, e'     => [
                'type'     => FlagType::STRING,
                'required' => true,
                'desc'      => 'the env name, eg: qa',
                'validator' => $v1 = new EnumValidator(['testing', 'qa']),
            ],
        ]);

        $str = $fs->toString();
        $this->assertStringContainsString('Allow: testing,qa', $str);
        $this->assertStringContainsString((string)$v1, $str);
    }
}
