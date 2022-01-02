<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Contract;

use Toolkit\PFlag\CliApp;
use Toolkit\PFlag\FlagsParser;

/**
 * interface CmdHandlerInterface
 *
 * @author inhere
 */
interface CmdHandlerInterface
{
    /**
     * @return array{name:string, desc: string, example:string, help: string}
     */
    public function metadata(): array;

    /**
     * @param FlagsParser $fs
     *
     * @return void
     */
    public function configure(FlagsParser $fs): void;

    /**
     * @param FlagsParser $fs
     * @param CliApp $app
     *
     * @return mixed
     */
    public function execute(FlagsParser $fs, CliApp $app): mixed;
}
