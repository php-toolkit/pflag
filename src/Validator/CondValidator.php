<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Closure;
use Toolkit\PFlag\AbstractFlags;

/**
 * class CondValidator
 */
abstract class CondValidator extends AbstractValidator
{
    /**
     * Before condition check.
     * if return false, will skip call checkInput();
     *
     * @var callable
     * @psalm-param Closure($fs AbstractFlags):bool
     */
    protected $cond;

    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function __invoke($value, string $name): bool
    {
        $condFn = $this->cond;
        if ($condFn && !$condFn($this->fs)) {
            return true;
        }

        return $this->checkInput($value, $name);
    }

    /**
     * @param mixed $cond
     *
     * @return AbstractValidator|static
     */
    public function setCond($cond): self
    {
        $this->cond = $cond;
        return $this;
    }
}
