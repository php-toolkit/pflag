<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Contract\FlagInterface;
use Toolkit\PFlag\Exception\FlagException;
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
    protected $name;

    /**
     * @var string
     */
    protected $desc = '';

    /**
     * The flag data type. (eg: 'int', 'bool', 'string', 'array', 'mixed')
     *
     * @var string
     */
    protected $type = FlagType::STRING;

    /**
     * The default value
     *
     * @var mixed
     */
    protected $default;

    /**
     * The flag value
     *
     * @var mixed
     */
    protected $value;

    /**
     * @var bool
     */
    protected $required = false;

    /**
     * The flag value validator
     * - if validate fail, please throw FlagException
     *
     * @var callable
     */
    protected $validator;

    /**
     * @param string     $name
     * @param string     $desc
     * @param bool       $required
     * @param mixed|null $default
     *
     * @return static|Argument|Option
     */
    public static function new(string $name, string $desc = '', bool $required = false, $default = null): self
    {
        return new static($name, $desc, $required, $default);
    }

    /**
     * Class constructor.
     *
     * @param string $name
     * @param string $desc
     * @param bool   $required
     * @param mixed  $default   The default value
     *                          - for Flag::ARG_OPTIONAL mode only
     *                          - must be null for Flag::OPT_BOOLEAN
     */
    public function __construct(string $name, string $desc = '', bool $required = false, $default = null)
    {
        $this->name = $name;

        $this->default  = $default;
        $this->required = $required;

        $this->setDesc($desc);
    }

    public function init(): void
    {
        if ($this->isArray()) {
            $this->type = FlagType::ARRAY;
        }
    }

    /**
     * @return bool
     */
    public function hasDefault(): bool
    {
        return $this->default !== null;
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
        // format value by type
        $value = FlagType::fmtBasicTypeValue($this->type, $value);

        // has validator
        if ($cb = $this->validator) {
            $ok  = true;
            $ret = $cb($value);

            if ($ret) {
                [$ok, $value] = $ret;
            }

            if (false === $ok) {
                throw new FlagException('invalid value for flag: ' . $this->getNameMark());
            }
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
    public function getNameMark(): string
    {
        return $this->name;
    }

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
        if (!FlagType::isValid($type)) {
            $name = $this->name;
            throw new FlagException("cannot define invalid flag type: $type(name: $name)");
        }

        $this->type = $type;
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
        if (!FlagHelper::isValidName($name)) {
            throw new FlagException('invalid flag name: ' . $name);
        }

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
            'name'     => $this->name,
            'desc'     => $this->desc,
            'type'     => $this->type,
            'default'  => $this->default,
            'required' => $this->required,
            'isArray'  => $this->isArray(),
        ];
    }

    /**
     * @return bool
     */
    public function isArray(): bool
    {
        // return $this->hasMode(Input::ARG_IS_ARRAY);
        return FlagType::isArray($this->type);
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->required === false;
    }

    /**
     * @param bool $required
     */
    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }
}
