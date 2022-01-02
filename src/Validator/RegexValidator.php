<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Exception\FlagException;
use function is_string;
use function preg_match;

/**
 * class RegexValidator
 */
class RegexValidator extends AbstractValidator
{
    public const ALPHA_NUM = '^\w+$';

    /**
     * Regex string. eg: '^\w+$'
     *
     * @var string
     */
    protected string $regex = '';

    /**
     * @param string $regex
     *
     * @return static
     */
    public static function new(string $regex): self
    {
        return new static($regex);
    }

    /**
     * Class constructor.
     *
     * @param string $regex
     */
    public function __construct(string $regex)
    {
        $this->setRegex($regex);
    }

    /**
     * Validate input value
     *
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput(mixed $value, string $name): bool
    {
        $regex = $this->regex;
        if (is_string($value) && preg_match("/$regex/", $value)) {
            return true;
        }

        throw new FlagException("flag '$name' value should match: $regex");
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        // return 'should match: ' . $this->regex;
        return '';
    }

    /**
     * @param string $regex
     */
    public function setRegex(string $regex): void
    {
        $this->regex = $regex;
    }
}
