<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlagTest\Cases;

use Toolkit\PFlag\CliApp;
use Toolkit\PFlag\Contract\CmdHandlerInterface;
use Toolkit\PFlag\FlagsParser;
use function vdump;

/**
 * class DemoCmdHandler
 *
 * @author inhere
 */
class DemoCmdHandler implements CmdHandlerInterface
{
    /**
     * @return array{name:string, desc: string, example:string, help: string}
     */
    public function metadata(): array
    {
        return [
            'name' => 'demo',
            'desc' => 'desc for demo command handler',
        ];
    }

    /**
     * @param FlagsParser $fs
     *
     * @return void
     */
    public function configure(FlagsParser $fs): void
    {
        $fs->addOptsByRules([
            'opt1' => 'string;a string opt1 for command test2, and is required;true',
            'opt2' => 'int;a int opt2 for command test2',
        ]);
    }

    /**
     * @param FlagsParser $fs
     * @param CliApp $app
     *
     * @return mixed
     */
    public function execute(FlagsParser $fs, CliApp $app): mixed
    {
        vdump(__METHOD__);
    }
}
