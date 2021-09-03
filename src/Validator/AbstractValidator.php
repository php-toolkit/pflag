<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\AbstractFlags;
use Toolkit\PFlag\Contract\ValidatorInterface;

/**
 * class AbstractValidator
 */
abstract class AbstractValidator implements ValidatorInterface
{
    /**
     * @var AbstractFlags
     */
    protected $fs;

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
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    abstract public function checkInput($value, string $name): bool;

    /**
     * @return AbstractFlags
     */
    public function getFs(): AbstractFlags
    {
        return $this->fs;
    }

    /**
     * @param AbstractFlags $fs
     */
    public function setFs(AbstractFlags $fs): void
    {
        $this->fs = $fs;
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
