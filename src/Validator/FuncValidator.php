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
 * class FuncValidator
 */
class FuncValidator extends AbstractValidator
{
    /**
     * @var callable(mixed, string): bool
     */
    protected $func;

    /**
     * @var string
     */
    protected string $tipMsg = '';

    /**
     * @param mixed $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput(mixed $value, string $name): bool
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
