<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

/**
 * class NameValidator
 */
class NameValidator extends RegexValidator
{
    public const DEFAULT_REGEX = '^\w[\w-]*$';

    /**
     * @var string
     */
    protected $regex = self::DEFAULT_REGEX;

    /**
     * @param string $regex
     *
     * @return static
     */
    public static function new(string $regex = self::DEFAULT_REGEX): parent
    {
        return new self($regex);
    }

    /**
     * Class constructor.
     *
     * @param string $regex
     */
    public function __construct(string $regex = self::DEFAULT_REGEX)
    {
        parent::__construct($regex);
    }

    /**
     * @param string $regex
     */
    public function setRegex(string $regex): void
    {
        if (!$regex) {
            return;
        }

        parent::setRegex($regex);
    }
}
