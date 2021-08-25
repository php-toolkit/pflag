<?php declare(strict_types=1);

namespace Toolkit\PFlag\Validator;

use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use function implode;
use function is_string;
use function preg_match;

/**
 * class RegexValidator
 */
class RegexValidator implements ValidatorInterface
{
    /**
     * Regex string. eg: '^\w+$'
     *
     * @var string
     */
    protected $regex = '';

    /**
     * @param string $regex
     *
     * @return static
     */
    public static function new(string $regex): self
    {
        return new static($regex);
    }

    /**
     * Class constructor.
     *
     * @param string $regex
     */
    public function __construct(string $regex)
    {
        $this->setRegex($regex);
    }

    /**
     * Validate input value
     *
     * @param mixed $value
     * @param string $name
     *
     * @return bool
     */
    public function __invoke($value, string $name): bool
    {
        $regex = $this->regex;
        if (is_string($value) && preg_match("/$regex/", $value)) {
            return true;
        }

        throw new FlagException("flag '$name' value should match: $regex" );
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return 'should match: ' . $this->regex;
    }

    /**
     * @param string $regex
     */
    public function setRegex(string $regex): void
    {
        $this->regex = $regex;
    }
}
