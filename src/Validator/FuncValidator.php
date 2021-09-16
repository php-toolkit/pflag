<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

/**
 * class FuncValidator
 */
class FuncValidator extends AbstractValidator
{
    /**
     * @var callable
     * @psalm-var callable(mixed, string): bool
     */
    protected $func;

    /**
     * @var string
     */
    protected $tipMsg = '';

    /**
     * @param mixed $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput($value, string $name): bool
    {
        $fn = $this->func;

        return $fn($value, $name);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->tipMsg;
    }

    /**
     * @param callable $func
     *
     * @return FuncValidator
     */
    public function setFunc(callable $func): self
    {
        $this->func = $func;
        return $this;
    }

    /**
     * @param string $tipMsg
     *
     * @return FuncValidator
     */
    public function setTipMsg(string $tipMsg): self
    {
        $this->tipMsg = $tipMsg;
        return $this;
    }
}
