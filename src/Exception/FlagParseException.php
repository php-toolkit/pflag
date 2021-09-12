<?php declare(strict_types=1);

namespace Toolkit\PFlag\Exception;

use Throwable;

/**
 * class FlagParseException
 */
class FlagParseException extends FlagException
{
    /**
     * @var string
     */
    public $flagType = 'option';

    public function __construct(string $message, int $code = 0, string $flagType = 'option')
    {
        $this->flagType = $flagType;

        parent::__construct($message, $code);
    }
}
