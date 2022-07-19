<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

use Toolkit\Cli\Cli;
use Toolkit\Cli\Util\LineParser;
use Toolkit\PFlag\Concern\HelperRenderTrait;
use Toolkit\PFlag\Concern\RuleParserTrait;
use Toolkit\PFlag\Contract\ParserInterface;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\Obj\Traits\NameAliasTrait;
use Toolkit\Stdlib\Obj\Traits\QuickInitTrait;
use Toolkit\Stdlib\Str;
use function array_merge;
use function array_shift;
use function array_values;
use function basename;
use function explode;
use function strpos;

/**
 * class FlagsParser
 * abstract parser
 */
abstract class FlagsParser implements ParserInterface
{
    use HelperRenderTrait;
    use QuickInitTrait;
    use NameAliasTrait;
    use RuleParserTrait;

    public const TRIM_CHARS    = "; \t\n\r\0\x0B";

    public const OPT_MAX_WIDTH = 16;

    public const RULE_SEP = ';';

    public const STATUS_OK = 0;

    public const STATUS_ARG = 1;

    public const STATUS_HELP = 2; // found `-h|--help` flag

    // public const STATUS_ERR = 3;

    /**
     * Special short option style
     *
     *  - gnu: `-abc` will expand: `-a -b -c`
     *  - posix: `-abc`  will expand: `-a=bc`
     */
    public const SHORT_STYLE_GUN   = 'gnu';

    public const SHORT_STYLE_POSIX = 'posix';

    public const DEFINE_ITEM = [
        'name'      => '',
        'desc'      => '',
        'type'      => FlagType::STRING,
        'helpType'  => '', // use for render help
        // 'index'    => 0, // only for argument
        'required'  => false,
        'envVar'    => '', // support read value from ENV var
        'default'   => null,
        'shorts'    => [], // only for option. ['a', 'b']
        'aliases'   => [], // only for option. ['cd', 'ef']
        'hidden'    => false, // only for option
        // value validator
        'validator' => null,
        // 'category' => null
    ];

    /**
     * If locked, cannot add option and argument
     *
     * @var bool
     */
    protected bool $locked = false;

    /**
     * @var bool Mark option is parsed
     */
    protected bool $parsed = false;

    /**
     * @var int
     */
    protected int $parseStatus = self::STATUS_OK;

    /**
     * The input flags
     *
     * @var string[]
     */
    protected array $flags = [];

    /**
     * The raw args, after option parsed from {@see $flags}
     *
     * @var string[]
     */
    protected array $rawArgs = [];

    /**
     * The overage raw args, after argument parsed from {@see $rawArgs}
     *
     * @var string[]
     */
    protected array $remainArgs = [];

    /**
     * The required option names.
     *
     * @var string[]
     */
    protected array $requiredOpts = [];

    // -------------------- settings for show help --------------------

    /**
     * The description. use for show help
     *
     * @var string
     */
    protected string $desc = '';

    /**
     * The bin script name. use for show help
     *
     * @var string
     */
    protected string $scriptName = '';

    /**
     * The bin script file. use for show help
     *
     * @var string
     */
    protected string $scriptFile = '';

    /**
     * settings and metadata information
     *
     * @var array
     */
    protected array $settings = [
        'hasShorts'      => false,
        // some setting for render help
        'argNameLen'     => 12,
        'optNameLen'     => 12,
        'descNlOnOptLen' => self::OPT_MAX_WIDTH,
        // more settings
        'exampleHelp'    => '',
        'moreHelp'       => '',
    ];

    /**
     * Delay call validators after parsed. TODO
     *
     * @var bool
     */
    // protected $delayValidate = false;

    // -------------------- settings for parse option --------------------

    /**
     * Special short option style
     *
     *  - gnu: `-abc` will expand: `-a -b -c`
     *  - posix: `-abc`  will expand: `-a=bc`
     *
     * @var string
     */
    protected string $shortStyle = self::SHORT_STYLE_GUN;

    /**
     * Stop parse option on found first argument.
     *
     * - Useful for support multi commands. eg: `top --opt ... sub --opt ...`
     *
     * @var bool
     */
    protected bool $stopOnFistArg = true;

    /**
     * Skip on found undefined option.
     *
     * - FALSE will throw FlagException error.
     * - TRUE  will skip it and collect as raw arg, then continue parse next.
     *
     * @var bool
     */
    protected bool $skipOnUndefined = false;

    // -------------------- settings for parse argument --------------------

    /**
     * Whether auto bind remaining args after option parsed
     *
     * @var bool
     */
    protected bool $autoBindArgs = true;

    /**
     * Strict match args number.
     * if exist unbind args, will throw FlagException
     *
     * @var bool
     */
    protected bool $strictMatchArgs = false;

    /**
     * Has array argument
     *
     * @var bool
     */
    protected bool $arrayArg = false;

    /**
     * Has optional argument
     *
     * @var bool
     */
    protected bool $optionalArg = false;

    /**
     * Class constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        Obj::init($this, $config);
    }

    /**
     * @param string $cmdline
     * @param bool $hasBin
     *
     * @return bool
     */
    public function parseCmdline(string $cmdline, bool $hasBin = true): bool
    {
        $flags = LineParser::parseIt($cmdline);

        if ($hasBin && $flags) {
            $sFile = array_shift($flags);
            $this->setScriptFile($sFile);
        }

        return $this->parse($flags);
    }

    /**
     * @param array|null $flags
     *
     * @return bool
     */
    public function parse(?array $flags = null): bool
    {
        if ($this->parsed) {
            return $this->parseStatus === self::STATUS_OK;
        }

        $this->parsed  = true;
        $this->rawArgs = [];

        if ($flags === null) {
            $flags = $_SERVER['argv'];
            $sFile = array_shift($flags);
            $this->setScriptFile($sFile);
        } else {
            $flags = array_values($flags);
        }

        $this->flags = $flags;
        return $this->doParse($flags);
    }

    /**
     * @param array $flags
     *
     * @return bool
     */
    abstract protected function doParse(array $flags): bool;

    /**
     * @param array $rawArgs
     *
     * @return array
     */
    protected function parseRawArgs(array $rawArgs): array
    {
        $args = [];

        // parse arguments
        foreach ($rawArgs as $arg) {
            // value specified inline (<arg>=<value>)
            if (strpos($arg, '=') > 0) {
                [$name, $value] = explode('=', $arg, 2);

                // ensure is valid name.
                if (FlagUtil::isValidName($name)) {
                    $args[$name] = $value;
                } else {
                    $args[] = $arg;
                }
            } else {
                $args[] = $arg;
            }
        }

        return $args;
    }

    /**
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getFirstArg(mixed $default = null): mixed
    {
        return $this->getArg(0, $default);
    }

    public function resetResults(): void
    {
        // clear match results
        $this->parsed  = false;
        $this->rawArgs = $this->flags = [];
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->isNotEmpty();
    }

    /**
     * @return bool
     */
    public function hasShortOpts(): bool
    {
        return $this->countAlias() > 0;
    }

    /****************************************************************
     * build and render help
     ***************************************************************/

    /**
     * display help messages
     */
    public function displayHelp(): void
    {
        if ($fn = $this->helpRenderer) {
            $fn($this);
            return;
        }

        Cli::println($this->buildHelp());
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->buildHelp();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->buildHelp();
    }

    /**
     * @param bool $withColor
     *
     * @return string
     */
    abstract public function buildHelp(bool $withColor = true): string;

    /**
     * @param string $name
     * @param string $sep
     *
     * @return string[]
     */
    public function getOptStrAsArray(string $name, string $sep = ','): array
    {
        $str = $this->getOpt($name);

        return $str ? Str::toNoEmptyArray($str, $sep) : [];
    }

    /**
     * @param string $name
     * @param string $sep
     *
     * @return int[]
     */
    public function getOptStrAsInts(string $name, string $sep = ','): array
    {
        $str = $this->getOpt($name);

        return $str ? Str::toInts($str, $sep) : [];
    }

    /****************************************************************
     * getter/setter methods
     ***************************************************************/

    /**
     * @return array
     */
    public function getRequiredOpts(): array
    {
        return $this->requiredOpts;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getScriptName();
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->setScriptName($name);
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
     * @param bool $setOnEmpty only set on desc is empty
     */
    public function setDesc(string $desc, bool $setOnEmpty = false): void
    {
        // only set on desc is empty
        if ($setOnEmpty && $this->desc) {
            return;
        }

        if ($desc) {
            $this->desc = $desc;
        }
    }

    /**
     * @return array
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * @return array
     */
    public function getRawArgs(): array
    {
        return $this->rawArgs;
    }

    /**
     * @return string[]
     */
    public function getRemainArgs(): array
    {
        return $this->remainArgs;
    }

    /**
     * @return string
     */
    public function popFirstRawArg(): string
    {
        return array_shift($this->rawArgs);
    }

    /**
     * @param bool $more
     *
     * @return array
     */
    public function getInfo(bool $more = false): array
    {
        $info = [
            'driver'     => static::class,
            'flags'      => $this->flags,
            'rawArgs'    => $this->rawArgs,
            'remainArgs' => $this->remainArgs,
            'opts'       => $this->getOpts(),
            'args'       => $this->getArgs(),
        ];

        if ($more) {
            $info['optRules'] = $this->getOptRules();
            $info['argRules'] = $this->getArgRules();
            $info['aliases']  = $this->getAliases();
        }

        return $info;
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function lock(): void
    {
        $this->locked = true;
    }

    public function unlock(): void
    {
        $this->locked = false;
    }

    /**
     * @param bool $locked
     */
    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    /**
     * @return bool
     */
    public function isParsed(): bool
    {
        return $this->parsed;
    }

    /**
     * @return int
     */
    public function getParseStatus(): int
    {
        return $this->parseStatus;
    }

    /**
     * @return bool
     */
    public function isStopOnFistArg(): bool
    {
        return $this->stopOnFistArg;
    }

    /**
     * @param bool $stopOnFistArg
     */
    public function setStopOnFistArg(bool $stopOnFistArg): void
    {
        $this->stopOnFistArg = $stopOnFistArg;
    }

    /**
     * @return bool
     */
    public function isSkipOnUndefined(): bool
    {
        return $this->skipOnUndefined;
    }

    /**
     * @param bool $skipOnUndefined
     */
    public function setSkipOnUndefined(bool $skipOnUndefined): void
    {
        $this->skipOnUndefined = $skipOnUndefined;
    }

    /**
     * @return bool
     */
    public function hasOptionalArg(): bool
    {
        return $this->optionalArg;
    }

    /**
     * @return bool
     */
    public function hasArrayArg(): bool
    {
        return $this->arrayArg;
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
    public function isStrictMatchArgs(): bool
    {
        return $this->strictMatchArgs;
    }

    /**
     * @param bool $strictMatchArgs
     */
    public function setStrictMatchArgs(bool $strictMatchArgs): void
    {
        $this->strictMatchArgs = $strictMatchArgs;
    }

    /**
     * @return string
     */
    public function getScriptFile(): string
    {
        return $this->scriptFile;
    }

    /**
     * @param string $scriptFile
     */
    public function setScriptFile(string $scriptFile): void
    {
        if ($scriptFile) {
            $this->scriptFile = $scriptFile;
            $this->scriptName = basename($scriptFile);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     */
    public function setSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    /**
     * @return string
     */
    public function getScriptName(): string
    {
        return $this->scriptName ?: FlagUtil::getBinName();
    }

    /**
     * @param string $scriptName
     */
    public function setScriptName(string $scriptName): void
    {
        $this->scriptName = $scriptName;
    }
}
