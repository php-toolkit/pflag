<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Closure;
use Toolkit\PFlag\FlagsParser;

/**
 * class CondValidator
 */
abstract class CondValidator extends AbstractValidator
{
    /**
     * @var FlagsParser
     */
    protected $fs;

    /**
     * Before condition check.
     * if return false, will skip call checkInput();
     *
     * @var callable
     * @psalm-param Closure($fs FlagsParser):bool
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
     * @return FlagsParser
     */
    public function getFs(): FlagsParser
    {
        return $this->fs;
    }

    /**
     * @param FlagsParser $fs
     */
    public function setFs(FlagsParser $fs): void
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
