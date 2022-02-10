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
use Toolkit\PFlag\Contract\FlagInterface;
use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\FlagUtil;
use Toolkit\PFlag\Validator\CondValidator;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\OS;
use function is_array;
use function is_bool;
use function is_scalar;
use function trim;

/**
 * Class Flag
 * - definition a input flag item(option|argument)
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
    protected string $name = '';

    /**
     * Flag description
     *
     * @var string
     */
    protected string $desc = '';

    /**
     * The flag data type. (eg: 'int', 'bool', 'string', 'array', 'mixed')
     *
     * @var string
     */
    protected string $type = FlagType::STRING;

    /**
     * @var string
     */
    protected string $helpType = '';

    /**
     * ENV var name. support read value from ENV var
     *
     * @var string
     */
    protected string $envVar = '';

    /**
     * The default value
     *
     * @var mixed|null
     */
    protected mixed $default = null;

    /**
     * The flag value
     *
     * @var mixed|null
     */
    protected mixed $value = null;

    // TODO category
    // protected $category = '';

    /**
     * @var bool
     */
    protected bool $required = false;

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
     * @param bool $required
     * @param mixed|null $default
     *
     * @return static
     */
    public static function new(
        string $name,
        string $desc = '',
        string $type = 'string',
        bool $required = false,
        mixed $default = null
    ): static {
        return new static($name, $desc, $type, $required, $default);
    }

    /**
     * Create by array define
     *
     * @param string $name
     * @param array $define
     *
     * @return static
     */
    public static function newByArray(string $name, array $define): static
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
     * @param bool $required
     * @param mixed|null $default The default value
     *                          - for Flag::ARG_OPTIONAL mode only
     *                          - must be null for Flag::OPT_BOOLEAN
     */
    public function __construct(
        string $name,
        string $desc = '',
        string $type = 'string',
        bool $required = false,
        mixed $default = null
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

        // support set value from ENV.
        if ($this->envVar && ($envVal = OS::getEnvVal($this->envVar))) {
            $this->value = FlagType::fmtBasicTypeValue($this->type, $envVal);
        }
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setTrustedValue(mixed $value): void
    {
        $this->value = $value;
    }

    /**
     * @param mixed $value
     */
    public function setValue(mixed $value): void
    {
        // format value by type
        $value = FlagType::fmtBasicTypeValue($this->type, $value);

        // has validator
        $cb = $this->validator;
        if ($cb && is_scalar($value)) {
            /** @see CondValidator::setFs() */
            // if (method_exists($cb, 'setFs')) {
            //     $cb->setFs($this);
            // }

            $ok  = true;
            $ret = $cb($value, $this->name);

            if (is_array($ret)) {
                [$ok, $value] = $ret;
            } elseif (is_bool($ret)) {
                $ok = $ret;
            }

            if (false === $ok) {
                $kind = $this->getKind();
                throw new FlagException("set invalid value for flag $kind: " . $this->getNameMark());
            }
        }

        if ($this->isArray()) {
            if (is_array($value)) {
                $this->value = $value;
            } else {
                $this->value[] = $value;
            }
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

    /**
     * @return string
     */
    public function getKind(): string
    {
        return FlagsParser::KIND_OPT;
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
            $kind = $this->getKind();
            $name = $this->getNameMark();
            throw new FlagException("invalid flag type '$type', $kind: $name");
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
        if (!FlagUtil::isValidName($name)) {
            throw new FlagException("invalid flag option name: $name");
        }

        $this->name = $name;
    }

    /**
     * @return array|false|float|int|string|null
     */
    public function getTypeDefault(): float|bool|int|array|string|null
    {
        return FlagType::getDefault($this->type);
    }

    /**
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * @param mixed $default
     */
    public function setDefault(mixed $default): void
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
            'name'      => $this->name,
            'desc'      => $this->desc,
            'type'      => $this->type,
            'default'   => $this->default,
            'envVar'    => $this->envVar,
            'required'  => $this->required,
            'validator' => $this->validator,
            'isArray'   => $this->isArray(),
            'helpType'  => $this->getHelpType(),
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
     * @param bool $useTypeOnEmpty
     *
     * @return string
     */
    public function getHelpType(bool $useTypeOnEmpty = false): string
    {
        if ($useTypeOnEmpty) {
            return $this->helpType ?: $this->type;
        }

        return $this->helpType;
    }

    /**
     * @param string $helpType
     */
    public function setHelpType(string $helpType): void
    {
        if ($helpType) {
            $this->helpType = $helpType;
        }
    }

    /**
     * @param callable|null $validator
     */
    public function setValidator(?callable $validator): void
    {
        if ($validator) {
            $this->validator = $validator;
        }
    }

    /**
     * @return callable|ValidatorInterface|null
     */
    public function getValidator(): callable|ValidatorInterface|null
    {
        return $this->validator;
    }

    /**
     * @return string
     */
    public function getEnvVar(): string
    {
        return $this->envVar;
    }

    /**
     * @param string $envVar
     */
    public function setEnvVar(string $envVar): void
    {
        $this->envVar = trim($envVar);
    }
}
