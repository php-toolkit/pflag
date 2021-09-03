<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use ArrayAccess;
use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Contract\FlagInterface;
use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagType;
use Toolkit\Stdlib\Obj;
use function is_array;
use function is_bool;

/**
 * Class Flag
 * - - definition a input flag item(option|argument)
 *
 * @package Toolkit\PFlag\Flag
 */
abstract class AbstractFlag implements ArrayAccess, FlagInterface
{
    use Obj\Traits\ArrayAccessByGetterSetterTrait;

    /**
     * Flag name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Flag description
     *
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
     * @var string
     */
    protected $showType = '';

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

    // TODO category
    // protected $category = '';

    /**
     * @var bool
     */
    protected $required = false;

    /**
     * The flag value validator
     * - if validate fail, please return for OR throw FlagException
     *
     * @var callable|ValidatorInterface|null
     * @psalm-var callable(mixed, string): bool
     */
    protected $validator;

    /**
     * @param string $name
     * @param string $desc
     * @param string $type
     * @param bool   $required
     * @param mixed  $default
     *
     * @return static|Argument|Option
     */
    public static function new(
        string $name,
        string $desc = '',
        string $type = 'string',
        bool $required = false,
        $default = null
    ): self {
        return new static($name, $desc, $type, $required, $default);
    }

    /**
     * @param string $name
     * @param array  $define
     *
     * @return static|Argument|Option
     */
    public static function newByArray(string $name, array $define): self
    {
        $flag = new static($name);
        if (isset($define['name'])) {
            unset($define['name']);
        }

        Obj::init($flag, $define);
        return $flag;
    }

    /**
     * Class constructor.
     *
     * @param string $name
     * @param string $desc
     * @param string $type
     * @param bool   $required
     * @param mixed  $default   The default value
     *                          - for Flag::ARG_OPTIONAL mode only
     *                          - must be null for Flag::OPT_BOOLEAN
     */
    public function __construct(
        string $name,
        string $desc = '',
        string $type = 'string',
        bool $required = false,
        $default = null
    ) {
        $this->default  = $default;
        $this->required = $required;

        $this->setName($name);
        $this->setType($type);
        $this->setDesc($desc);
    }

    public function init(): void
    {
        // init default value.
        if ($this->default !== null) {
            $this->default = FlagType::fmtBasicTypeValue($this->type, $this->default);
            $this->value   = $this->default;
        }
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
            $ret = $cb($value, $this->name);

            if (is_array($ret)) {
                [$ok, $value] = $ret;
            } elseif (is_bool($ret)) {
                $ok = $ret;
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
     * @return bool
     */
    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * @return bool
     */
    public function hasDefault(): bool
    {
        return $this->default !== null;
    }

    /**
     * @return string
     */
    public function getNameMark(): string
    {
        return $this->name;
    }

    /******************************************************************
     * getter/setter methods
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
        if (!$type) {
            return;
        }

        if (!FlagType::isValid($type)) {
            $name = $this->getNameMark();
            throw new FlagException("cannot define invalid flag type: $type $name");
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
            'showType' => $this->getShowType(),
        ];
    }

    /**
     * @return bool
     */
    public function isArray(): bool
    {
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

    /**
     * @return string
     */
    public function getShowType(): string
    {
        return $this->showType ?: $this->type;
    }

    /**
     * @param string $showType
     */
    public function setShowType(string $showType): void
    {
        $this->showType = $showType;
    }

    /**
     * @param callable|null $validator
     */
    public function setValidator(?callable $validator): void
    {
        $this->validator = $validator;
    }
}
