<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Exception;

use Toolkit\PFlag\FlagsParser;

/**
 * class FlagParseException
 */
class FlagParseException extends FlagException
{
    /**
     * @var string
     */
    public string $flagType = FlagsParser::KIND_OPT;

    public function __construct(string $message, int $code = 0, string $flagType = FlagsParser::KIND_OPT)
    {
        $this->flagType = $flagType;

        parent::__construct($message, $code);
    }
}
