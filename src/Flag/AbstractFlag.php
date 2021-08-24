<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use Toolkit\PFlag\Contract\FlagInterface;
use Toolkit\PFlag\FlagType;

/**
 * Class Flag
 * - - definition a input flag item(option|argument)
 *
 * @package Toolkit\PFlag\Flag
 */
abstract class AbstractFlag implements FlagInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $desc;

    /**
     * @var int
     */
    private $mode;

    /**
     * The flag data type. (eg: 'int', 'bool', 'string', 'array', 'mixed')
     *
     * @var string
     */
    private $type = FlagType::UNKNOWN;

    /**
     * The default value
     *
     * @var mixed
     */
    private $default;

    /**
     * The flag value
     *
     * @var mixed
     */
    private $value;

    /**
     * The flag value validator
     * - if validate fail, please throw FlagException
     *
     * @var callable
     */
    private $validator;

    /**
     * @param string $name
     * @param string $desc
     * @param int    $mode see Flag::ARG_* or Flag::OPT_*
     * @param mixed|null   $default
     *
     * @return static|Argument|Option
     */
    public static function new(string $name, string $desc = '', int $mode = 0, $default = null): self
    {
        return new static($name, $desc, $mode, $default);
    }

    /**
     * Class constructor.
     *
     * @param string $name
     * @param string $desc
     * @param int    $mode      see Flag::ARG_* or Flag::OPT_*
     * @param mixed  $default   The default value
     *                          - for Flag::ARG_OPTIONAL mode only
     *                          - must be null for Flag::OPT_BOOLEAN
     */
    public function __construct(string $name, string $desc = '', int $mode = 0, $default = null)
    {
        $this->name = $name;
        $this->mode = $mode;

        $this->default = $default;
        $this->setDesc($desc);
    }

    public function init(): void
    {
        if ($this->isArray()) {
            $this->type = FlagType::ARRAY;
        }
    }

    /******************************************************************
     * mode value
     *****************************************************************/

    /**
     * @param int $mode
     *
     * @return bool
     */
    public function hasMode(int $mode): bool
    {
        return ($this->mode & $mode) > 0;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        // filter value by type
        switch ($this->type) {
            case FlagType::INT:
                $value = (int)$value;
                break;
            case FlagType::BOOL:
                $value = (bool)$value;
                break;
            case FlagType::FLOAT:
                $value = (float)$value;
                break;
            case FlagType::STRING:
                $value = (string)$value;
                break;
            // case FlagType::ARRAY:
            //     $value = (string)$value;
            //     break;
            default:
                // nothing
                break;
        }

        // has validator
        if ($cb = $this->validator) {
            $value = $cb($value);
            // if (false === $ok) {
            //     throw new FlagException('');
            // }
        }

        if ($this->isArray()) {
            $this->value[] = $value;
        } else {
            $this->value = $value;
        }
    }

    /**
     * @param callable $validator
     */
    public function setValidator(callable $validator): void
    {
        $this->validator = $validator;
    }

    /******************************************************************
     *
     *****************************************************************/

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * @param int $mode
     */
    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default): void
    {
        $this->default = $default;
    }

    /**
     * @return string
     */
    public function getDesc(): string
    {
        return $this->desc;
    }

    /**
     * @param string $desc
     */
    public function setDesc(string $desc): void
    {
        $this->desc = $desc;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'mode'        => $this->mode,
            'type'        => $this->type,
            'default'     => $this->default,
            'isArray'     => $this->isArray(),
            'isOptional'  => $this->isOptional(),
            'isRequired'  => $this->isRequired(),
            'description' => $this->desc,
        ];
    }
}
