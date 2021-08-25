<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\Stdlib\Str;
use function implode;
use function in_array;

/**
 * class EnumValidator
 */
class EnumValidator implements ValidatorInterface
{
    /**
     * @var array
     */
    protected $enums = [];

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
    public function __invoke($value, string $name): bool
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
        return 'allow: ' . implode(',', $this->enums);
    }
}
