<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use function is_string;
use function trim;

/**
 * class EmptyValidator
 */
class EmptyValidator implements ValidatorInterface
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
    public function __invoke($value, string $name): bool
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
        return 'Not empty';
    }
}
