<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Contract\ValidatorInterface;

/**
 * class AbstractValidator
 */
abstract class AbstractValidator implements ValidatorInterface
{
    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function __invoke(mixed $value, string $name): bool
    {
        return $this->checkInput($value, $name);
    }

    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    abstract public function checkInput(mixed $value, string $name): bool;
}
