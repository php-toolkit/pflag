<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\Traits\FlagArgumentsTrait;
use Toolkit\PFlag\Traits\FlagOptionsTrait;
use function array_shift;
use function array_slice;
use function count;
use function ltrim;
use function strlen;
use function substr;

/**
 * Class Flags
 *
 * @package Toolkit\PFlag
 */
class Flags extends AbstractParser
{
    use FlagArgumentsTrait;

    use FlagOptionsTrait;

    /**
     * @var self
     */
    private static $std;

    /**
     * @var callable
     */
    private $helpRenderer;

    /**
     * @var bool
     */
    private $autoBindArgs = false;

    /**
     * @return $this
     */
    public static function std(): self
    {
        if (!self::$std) {
            self::$std = new self();
        }

        return self::$std;
    }

    /**
     * @param array|null $args
     *
     * @return self
     */
    public static function parseArgs(array $args = null): self
    {
        return (new self())->parse($args);
    }

    /**************************************************************************
     * parse command option flags
     **************************************************************************/

    /**
     * @var string
     */
    private $curOptKey = '';

    private $parseStatus = self::STATUS_OK;

    public const STATUS_OK = 0;

    public const STATUS_ERR = 1;

    public const STATUS_END = 2;

    public const STATUS_HELP = 3; // found `-h|--help` flag

    /**
     * @param array|null $args
     *
     * @return self
     */
    public function parse(array $args = null): self
    {
        if ($args === null) {
            $args = $_SERVER['argv'];
            array_shift($args);
        }

        $this->parsed  = true;
        $this->rawArgs = $this->flags = $args;

        $breakStatus = self::STATUS_OK;
        while (true) {
            [$goon, $status] = $this->parseOne();
            if ($goon) {
                continue;
            }

            if (self::STATUS_HELP === $status) {
                // TODO show help
                break;
            }

            if (self::STATUS_OK === $status) {
                break;
            }
        }

        if ($breakStatus === self::STATUS_HELP) {
            // TODO show help

            return $this;
        }

        // check required opts
        if ($this->requiredOpts) {
            foreach ($this->requiredOpts as $name) {
                if (!isset($this->opts[$name])) {
                    throw new FlagException("flag option '$name' is required");
                }
            }
        }

        // binding remaining args.
        if ($this->autoBindArgs) {
            $this->bindingArguments();
        }

        return $this;
    }

    /**
     * parse one flag.
     *
     * will stop on:
     * - found `-h|--help` flag
     * - found first arg(not an option)
     *
     * @return array [goon: bool, status: int]
     */
    protected function parseOne(): array
    {
        if (!$args = $this->rawArgs) {
            return [false, self::STATUS_OK];
        }

        $arg = array_shift($this->rawArgs);

        // NOTICE: will stop parse option on found '--'
        if ($arg === '--') {
            return [false, self::STATUS_OK];
        }

        // empty.
        // is not an option.
        if ('' === $arg || $arg[0] !== '-') {
            $this->rawArgs = $args; // revert args on exit
            return [false, self::STATUS_OK];
        }

        $name = ltrim($arg, '-');

        // invalid option name as argument. eg: '- '
        if ('' === $name) {
            $this->rawArgs = $args; // revert args on exit
            return [false, self::STATUS_OK];
        }

        // show help
        if ($name === 'h' || $name === 'help') {
            return [false, self::STATUS_HELP];
        }

        $value  = '';
        $hasVal = false;

        $len = strlen($name);
        for ($i = 0; $i < $len; $i++) {
            if ($name[$i] === '=') {
                $hasVal = true;
                $name   = substr($name, 0, $i);

                // fix: `--name=` no value string.
                if ($i + 1 < $len) {
                    $value = substr($name, $i + 1);
                }
            }
        }

        $rName = $this->resolveAlias($name);
        if (!isset($this->defined[$rName])) {
            throw new FlagException("flag option provided but not defined: $arg", 404);
        }

        $opt = $this->defined[$rName];

        // bool option default always set TRUE.
        if ($opt->isBoolean()) {
            $boolVal = true;
            if ($hasVal) {
                // only allow set bool value by --opt=false
                $boolVal = FlagHelper::str2bool($value);
            }

            $opt->setValue($boolVal);
        } else {
            if (!$hasVal && count($this->rawArgs) > 0) {
                // value is next arg
                $hasVal = true;
                $ntArg  = $this->rawArgs[0];

                // is not an option value.
                if ($ntArg[0] === '-') {
                    $hasVal = false;
                } else {
                    $value = array_shift($this->rawArgs);
                }
            }

            if (!$hasVal) {
                throw new FlagException("flag option '$arg' needs an value", 400);
            }

            // set value
            $opt->setValue($value);
        }

        $this->addMatched($opt);
        return [true, self::STATUS_OK];
    }

    /**
     * @param bool $clearDefined
     */
    public function reset(bool $clearDefined = false): void
    {
        if ($clearDefined) {
            $this->defined = [];
            $this->resetArguments();
        }

        // clear match results
        $this->parsed  = false;
        $this->matched = [];
        $this->rawArgs = $this->rawArgs = [];
    }

    /**************************************************************************
     * parse and binding command arguments
     **************************************************************************/

    /**
     * Parse and binding command arguments
     *
     * NOTICE: must call it on options parsed.
     */
    public function bindingArguments(): self
    {
        // parse arguments
        $args = $this->parseRawArgs();

        // collect argument values
        foreach ($this->arguments as $index => $arg) {
            if (!isset($args[$index]) && $arg->isRequired()) {
                $mark = $arg->getNameMark();
                throw new FlagException("flag argument $mark is required");
            }

            if ($arg->isArray()) {
                // remain args
                $values = array_slice($args, $index);

                foreach ($values as $value) {
                    $arg->setValue($value);
                }
            } else {
                $arg->setValue($args[$index]);
            }
        }

        return $this;
    }

    /**
     * @return callable
     */
    public function getHelpRenderer(): callable
    {
        return $this->helpRenderer;
    }

    /**
     * @param callable $helpRenderer
     */
    public function setHelpRenderer(callable $helpRenderer): void
    {
        $this->helpRenderer = $helpRenderer;
    }

    /**
     * @return bool
     */
    public function isAutoBindArgs(): bool
    {
        return $this->autoBindArgs;
    }

    /**
     * @param bool $autoBindArgs
     */
    public function setAutoBindArgs(bool $autoBindArgs): void
    {
        $this->autoBindArgs = $autoBindArgs;
    }

    /**
     * @return bool
     */
    public function isStopOnUndefined(): bool
    {
        return $this->stopOnUndefined;
    }

    /**
     * @param bool $stopOnUndefined
     */
    public function setStopOnUndefined(bool $stopOnUndefined): void
    {
        $this->stopOnUndefined = $stopOnUndefined;
    }
}
