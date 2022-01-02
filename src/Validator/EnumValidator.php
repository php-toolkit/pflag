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
use Toolkit\Stdlib\Str;
use function implode;
use function in_array;

/**
 * class EnumValidator
 */
class EnumValidator extends AbstractValidator
{
    /**
     * @var array
     */
    protected array $enums = [];

    /**
     * @param array $enums
     *
     * @return static
     */
    public static function new(array $enums): self
    {
        return new static($enums);
    }

    /**
     * @param string $str
     * @param string $sep
     *
     * @return static
     */
    public static function newByString(string $str, string $sep = ','): self
    {
        return new static(Str::explode($str, $sep));
    }

    /**
     * Class constructor.
     *
     * @param array $enums
     */
    public function __construct(array $enums)
    {
        $this->enums = $enums;
    }

    /**
     * @param mixed $value
     * @param string $name
     *
     * @return bool
     */
    public function checkInput(mixed $value, string $name): bool
    {
        if (in_array($value, $this->enums, true)) {
            return true;
        }

        throw new FlagException("flag '$name' value must be in: " . implode(',', $this->enums));
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return 'Allow: ' . implode(',', $this->enums);
    }
}
