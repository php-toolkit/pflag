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
use function count;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;
use function trim;

/**
 * class LenValidator
 */
class LenValidator extends AbstractValidator
{
    /**
     * @var int|null
     */
    protected ?int $min;

    /**
     * @var int|null
     */
    protected ?int $max;

    /**
     * @param int|null $min
     * @param int|null $max
     *
     * @return static
     */
    public static function new(int $min = null, int $max = null): self
    {
        return new static($min, $max);
    }

    /**
     * Class constructor.
     *
     * @param int|null $min
     * @param int|null $max
     */
    public function __construct(int $min = null, int $max = null)
    {
        $this->min = $min;
        $this->max = $max;
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
            $len = strlen(trim($value));
        } elseif (is_array($value)) {
            $len = count($value);
        } else {
            return false;
        }

        // if ($this->min !== null && $this->max !== null) {
        //     return sprintf('Len: %d - %d', $this->min, $this->max);
        // }
        //
        // if ($this->min !== null) {
        //     return sprintf('Len: >= %d', $this->min);
        // }
        //
        // if ($this->max !== null) {
        //     return sprintf('Len: <= %d', $this->max);
        // }

        // if (empty($value)) {
        //     throw new FlagException("flag '$name' value cannot be empty");
        // }

        return true;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if ($this->min !== null && $this->max !== null) {
            return sprintf('Len: %d - %d', $this->min, $this->max);
        }

        if ($this->min !== null) {
            return sprintf('Len: >= %d', $this->min);
        }

        if ($this->max !== null) {
            return sprintf('Len: <= %d', $this->max);
        }

        // not limit
        return '';
    }
}
