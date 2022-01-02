<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

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
    protected string $regex = self::DEFAULT_REGEX;

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
