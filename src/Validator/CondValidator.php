<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\FlagsParser;

/**
 * class CondValidator
 */
abstract class CondValidator extends AbstractValidator
{
    /**
     * @var FlagsParser|null
     */
    protected ?FlagsParser $fs = null;

    /**
     * Before condition check.
     * if return false, will skip call checkInput();
     *
     * @var callable(FlagsParser):bool
     */
    protected $condFn;

    /**
     * @param mixed  $value
     * @param string $name
     *
     * @return bool
     */
    public function __invoke(mixed $value, string $name): bool
    {
        $condFn = $this->condFn;
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
     * @param mixed $condFn
     *
     * @return static
     */
    public function setCondFn(mixed $condFn): self
    {
        $this->condFn = $condFn;
        return $this;
    }
}
