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
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\SFlags;

/**
 * Class SFlagsTest
 *
 * @package Toolkit\PFlagTest\Flag
 */
class SFlagsTest extends BaseFlagsTestCase
{
    public function testParseDefined(): void
    {
        $fs = SFlags::new();
        $this->assertFalse($fs->isParsed());
        $this->assertTrue($fs->isStopOnFistArg());

        // string
        $flags = ['--name', 'inhere', 'arg0', 'arg1'];
        $fs->parseDefined($flags, [
            'name', // string
        ]);

        $this->assertTrue($fs->isParsed());
        $this->assertCount(2, $fs->getRawArgs());
        $this->assertSame('inhere', $fs->getOption('name'));
        $this->assertNotEmpty($fs->getOptRules());
        $this->assertNotEmpty($fs->getOptDefines());
        $this->assertEmpty($fs->getArgRules());
        $this->assertEmpty($fs->getArgDefines());

        $fs->reset();
        $this->assertFalse($fs->isParsed());
        // vdump($fs);

        // int
        $flags = ['-n', 'inhere', '--age', '99'];
        $fs->parseDefined($flags, [
            // 'name,n' => FlagType::STRING, // add an alias
            'n,name' => FlagType::STRING, // add an alias
            'age'    => FlagType::INT,
        ]);
        // vdump($fs);
        $this->assertSame('inhere', $fs->getOption('name'));
        $this->assertSame(99, $fs->getOption('age'));
        $this->assertCount(0, $fs->getRawArgs());
        $this->assertTrue($fs->hasAlias('n'));
        $this->assertSame('name', $fs->resolveAlias('n'));

        $fs->reset();
        $this->assertFalse($fs->isParsed());

        // bool
        $flags = ['--name', 'inhere', '-f', 'arg0'];
        $fs->parseDefined($flags, [
            'name', // string
            'f' => FlagType::BOOL,
        ]);
        $this->assertTrue($fs->getOpt('f'));
        $this->assertSame('inhere', $fs->getOption('name'));
        $this->assertCount(1, $fs->getRawArgs());

        $fs->reset();
        $this->assertFalse($fs->isParsed());

        // array
        $flags = ['--name', 'inhere', '--tags', 'php', '-t', 'go', '--tags', 'java', '-f', 'arg0'];
        $fs->parseDefined($flags, [
            'name', // string
            'tags,t' => FlagType::ARRAY,
            'f'      => FlagType::BOOL,
        ]);
        // vdump($fs);
        $this->assertTrue($fs->getOpt('f'));
        $this->assertSame('inhere', $fs->getOption('name'));
        // [php, go, java]
        $this->assertIsArray($tags = $fs->getOption('tags'));
        $this->assertCount(3, $tags);
        $this->assertCount(1, $rArgs = $fs->getRawArgs());
        $this->assertCount(0, $fs->getArgs());
        $this->assertSame('arg0', $rArgs[0]);
        // vdump($rArgs, $fs->getOpts());

        $fs->reset();
        $this->assertFalse($fs->isParsed());

        // ints
        $flags = ['--id', '23', '--id', '45'];
        $fs->parseDefined($flags, [
            'id' => FlagType::INTS,
        ]);
        // [23, 45]
        $this->assertIsArray($ids = $fs->getOption('id'));
        $this->assertCount(2, $ids);
        $this->assertSame([23, 45], $ids);
        $this->assertCount(0, $fs->getRawArgs());
        // vdump($fs->getOpts(), $fs->getArgs());

        $fs->reset();
        $this->assertFalse($fs->isParsed());

        // parse undefined
        $flags = ['--name', 'inhere'];
        $this->expectException(FlagException::class);
        $this->expectExceptionMessage('flag option provided but not defined: --name');
        $fs->parseDefined($flags, []);
    }

    public function testOptRule_required(): void
    {
        $fs = SFlags::new();
        $this->assertFalse($fs->isParsed());
        $this->assertTrue($fs->isStopOnFistArg());

        $flags = ['--name', 'inhere'];
        $fs->parseDefined($flags, [
            'name' => 'string;;required',
        ]);
        $this->assertNotEmpty($req = $fs->getRequiredOpts());
        $this->assertCount(1, $req);
        $this->assertSame('inhere', $fs->getOpt('name'));
        $fs->reset();

        $this->expectException(FlagException::class);
        $this->expectExceptionMessage("flag option 'name' is required");
        $fs->setOptRules([
            'name' => 'string;;required',
        ]);
        $fs->parse([]);
    }
}
