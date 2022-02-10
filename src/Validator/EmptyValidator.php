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
use function trim;

/**
 * class EmptyValidator
 */
class EmptyValidator extends AbstractValidator
{
    /**
     * @return static
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput(mixed $value, string $name): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (empty($value)) {
            throw new FlagException("flag '$name' value cannot be empty");
        }

        return true;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }
}
