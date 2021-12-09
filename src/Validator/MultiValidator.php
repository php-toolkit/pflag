<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Contract\ValidatorInterface;

/**
 * class MultiValidator
 */
class MultiValidator extends AbstractValidator
{
    /**
     * @var ValidatorInterface[]
     */
    private array $validators;

    /**
     * @param array $validators
     *
     * @return static
     */
    public static function new(array $validators): self
    {
        return new self($validators);
    }

    /**
     * Class constructor.
     *
     * @param array $validators
     */
    public function __construct(array $validators)
    {
        $this->validators = $validators;
    }

    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput(mixed $value, string $name): bool
    {
        foreach ($this->validators as $validator) {
            $ok = $validator($value, $name);
            if ($ok === false) {
                return false;
            }
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
