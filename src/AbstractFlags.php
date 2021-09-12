<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use Toolkit\Cli\Cli;
use Toolkit\Cli\Color\ColorTag;
use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Concern\RuleParserTrait;
use Toolkit\PFlag\Contract\ParserInterface;
use Toolkit\PFlag\Contract\ValidatorInterface;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\Stdlib\Helper\DataHelper;
use Toolkit\Stdlib\Helper\IntHelper;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\Obj\Traits\NameAliasTrait;
use Toolkit\Stdlib\Obj\Traits\QuickInitTrait;
use Toolkit\Stdlib\Str;
use function array_merge;
use function array_shift;
use function array_values;
use function basename;
use function count;
use function explode;
use function is_object;
use function ksort;
use function method_exists;
use function sprintf;
use function strlen;
use function strpos;
use function trim;

/**
 * class AbstractFlags
 * abstract parser
 */
abstract class AbstractFlags implements ParserInterface
{
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
        'showType'  => '', // use for show help
        // 'index'    => 0, // only for argument
        'required'  => false,
        'envVar'    => '', // support read value from ENV var
        'default'   => null,
        'shorts'    => [], // only for option. ['a', 'b']
        // value validator
        'validator' => null,
        // 'category' => null
    ];

    /**
     * @var bool Mark option is parsed
     */
    protected $parsed = false;

    /**
     * @var int
     */
    protected $parseStatus = self::STATUS_OK;

    /**
     * The input flags
     *
     * @var string[]
     */
    protected $flags = [];

    /**
     * The remaining raw args, after option parsed from {@see $rawFlags}
     *
     * @var string[]
     */
    protected $rawArgs = [];

    /**
     * The required option names.
     *
     * @var string[]
     */
    protected $requiredOpts = [];

    // -------------------- settings for show help --------------------

    /**
     * The description. use for show help
     *
     * @var string
     */
    protected $desc = '';

    /**
     * The bin script name. use for show help
     *
     * @var string
     */
    protected $scriptName = '';

    /**
     * The bin script file. use for show help
     *
     * @var string
     */
    protected $scriptFile = '';

    /**
     * settings and metadata information
     *
     * @var array
     */
    protected $settings = [
        'hasShorts'      => false,
        // some setting for render help
        'argNameLen'     => 12,
        'optNameLen'     => 12,
        'descNlOnOptLen' => self::OPT_MAX_WIDTH,
    ];

    /**
     * Delay call validators after parsed. TODO
     *
     * @var bool
     */
    protected $delayValidate = false;

    // -------------------- settings for parse option --------------------

    /**
     * Special short option style
     *
     *  - gnu: `-abc` will expand: `-a -b -c`
     *  - posix: `-abc`  will expand: `-a=bc`
     *
     * @var string
     */
    protected $shortStyle = self::SHORT_STYLE_GUN;

    /**
     * Stop parse option on found first argument.
     *
     * - Useful for support multi commands. eg: `top --opt ... sub --opt ...`
     *
     * @var bool
     */
    protected $stopOnFistArg = true;

    /**
     * Skip on found undefined option.
     *
     * - FALSE will throw FlagException error.
     * - TRUE  will skip it and collect as raw arg, then continue parse next.
     *
     * @var bool
     */
    protected $skipOnUndefined = false;

    // -------------------- settings for parse argument --------------------

    /**
     * Has array argument
     *
     * @var bool
     */
    protected $arrayArg = false;

    /**
     * Has optional argument
     *
     * @var bool
     */
    protected $optionalArg = false;

    /**
     * @var bool
     */
    protected $autoBindArgs = true;

    /**
     * @var bool
     */
    protected $strictCheckArgs = false;

    // -------------------- settings for render help --------------------

    /**
     * Auto render help on provide '-h', '--help'
     *
     * @var bool
     */
    protected $autoRenderHelp = true;

    /**
     * Show flag data type on render help
     *
     * @var bool
     */
    protected $showTypeOnHelp = true;

    /**
     * Custom help renderer.
     *
     * @var callable
     */
    protected $helpRenderer;

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
     * @param array|null $flags
     *
     * @return bool
     */
    public function parse(?array $flags = null): bool
    {
        if ($this->parsed) {
            return true;
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
                if (FlagHelper::isValidName($name)) {
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

    public function resetResults(): void
    {
        // clear match results
        $this->parsed  = false;
        $this->rawArgs = $this->flags = [];
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
     * @param array $argDefines
     * @param array $optDefines
     * @param bool  $withColor
     *
     * @return string
     */
    protected function doBuildHelp(array $argDefines, array $optDefines, bool $withColor): string
    {
        $buf = Str\StrBuffer::new();

        // ------- desc -------
        if ($title = $this->desc) {
            $buf->writeln(Str::ucfirst($title) . "\n");
        }

        $hasArgs = count($argDefines) > 0;
        $hasOpts = count($optDefines) > 0;

        // ------- usage -------
        $binName = $this->scriptName ?: FlagUtil::getBinName();
        if ($hasArgs || $hasOpts) {
            $buf->writeln("<ylw>Usage:</ylw> $binName [Options ...] -- [Arguments ...]\n");
        }

        // ------- args -------
        $nameTag = 'info';
        $fmtArgs = $this->buildArgsForHelp($argDefines);

        if ($hasArgs) {
            $buf->writeln('<ylw>Arguments:</ylw>');
        }

        $nameLen = $this->settings['argNameLen'];
        foreach ($fmtArgs as $hName => $arg) {
            [$desc, $lines] = $this->formatDesc($arg);

            // write to buffer.
            $hName = Str::padRight($hName, $nameLen);
            $buf->writef("  <%s>%s</%s>    %s\n", $nameTag, $hName, $nameTag, $desc);

            // remaining desc lines
            if ($lines) {
                $indent = Str::repeat(' ', $nameLen);
                foreach ($lines as $line) {
                    $buf->writef("     %s%s\n", $indent, $line);
                }
            }
        }

        $hasArgs && $buf->writeln('');

        // ------- opts -------
        if ($hasOpts) {
            $buf->writeln('<ylw>Options:</ylw>');
        }

        $nameTag = 'info';
        $fmtOpts = $this->buildOptsForHelp($optDefines);

        $nameLen  = $this->settings['optNameLen'];
        $maxWidth = $this->settings['descNlOnOptLen'];
        foreach ($fmtOpts as $hName => $opt) {
            [$desc, $lines] = $this->formatDesc($opt);

            // need echo desc at newline.
            $hName = Str::padRight($hName, $nameLen);
            if (strlen($hName) > $maxWidth) {
                $buf->writef("  <%s>%s</%s>\n", $nameTag, $hName, $nameTag);
                $buf->writef("     %s%s\n", Str::repeat(' ', $nameLen), $desc);
            } else {
                $buf->writef("  <%s>%s</%s>   %s\n", $nameTag, $hName, $nameTag, $desc);
            }

            // remaining desc lines
            if ($lines) {
                $indent = Str::repeat(' ', $nameLen);
                foreach ($lines as $line) {
                    $buf->writef("     %s%s\n", $indent, $line);
                }
            }
        }

        return $withColor ? $buf->clear() : ColorTag::clear($buf->clear());
    }

    /**
     * @param array|Option|Argument $define
     *
     * @return array
     * @see DEFINE_ITEM for array $define
     */
    protected function formatDesc($define): array
    {
        $desc = $define['desc'];

        if ($define['required']) {
            $desc = '<red1>*</red1>' . $desc;
        }

        // validator limit
        if (!empty($define['validator'])) {
            $v = $define['validator'];

            /** @see ValidatorInterface */
            if (is_object($v) && method_exists($v, '__toString')) {
                $limit = (string)$v;
                $desc  .= $limit ? ' ' . $limit : '';
            }
        }

        // default value.
        if (isset($define['default']) && $define['default'] !== null) {
            $desc .= sprintf('(default <mga>%s</mga>)', DataHelper::toString($define['default']));
        }

        // desc has multi line
        $lines = [];
        if (strpos($desc, "\n") > 0) {
            $lines = explode("\n", $desc);
            $desc  = array_shift($lines);
        }

        return [$desc, $lines];
    }

    /**
     * @param array $argDefines
     *
     * @return array
     */
    protected function buildArgsForHelp(array $argDefines): array
    {
        $fmtArgs = [];
        $maxLen  = $this->settings['argNameLen'];

        /** @var array|Argument $arg {@see DEFINE_ITEM} */
        foreach ($argDefines as $arg) {
            $helpName = $arg['name'] ?: 'arg' . $arg['index'];
            if ($desc = $arg['desc']) {
                $desc = trim($desc);
            }

            // ensure desc is not empty
            $arg['desc'] = $desc ? Str::ucfirst($desc) : "Argument $helpName";

            $type = $arg['type'];
            if (FlagType::isArray($type)) {
                $helpName .= '...';
            }

            if ($this->showTypeOnHelp) {
                $typeName = FlagType::getHelpName($type);
                $helpName .= $typeName ? " $typeName" : '';
            }

            $maxLen = IntHelper::getMax($maxLen, strlen($helpName));

            // append
            $fmtArgs[$helpName] = $arg;
        }

        $this->settings['argNameLen'] = $maxLen;
        return $fmtArgs;
    }

    /**
     * @param array $optDefines
     *
     * @return array
     */
    protected function buildOptsForHelp(array $optDefines): array
    {
        if (!$optDefines) {
            return [];
        }

        $fmtOpts = [];
        $nameLen = $this->settings['optNameLen'];
        ksort($optDefines);

        /** @var array|Option $opt {@see DEFINE_ITEM} */
        foreach ($optDefines as $name => $opt) {
            $names = $opt['shorts'];
            /** @see Option support alias name. */
            if (isset($opt['alias']) && $opt['alias']) {
                $names[] = $opt['alias'];
            }
            // real name.
            $names[] = $name;

            if ($desc = $opt['desc']) {
                $desc = trim($desc);
            }

            // ensure desc is not empty
            $opt['desc'] = $desc ? Str::ucfirst($desc) : "Option $name";

            $helpName = FlagUtil::buildOptHelpName($names);
            if ($this->showTypeOnHelp) {
                $typeName = FlagType::getHelpName($opt['type']);
                $helpName .= $typeName ? " $typeName" : '';
            }

            $nameLen = IntHelper::getMax($nameLen, strlen($helpName));
            // append
            $fmtOpts[$helpName] = $opt;
        }

        // limit option name width
        $maxLen = IntHelper::getMax($this->settings['descNlOnOptLen'], self::OPT_MAX_WIDTH);

        $this->settings['descNlOnOptLen'] = $maxLen;
        // set opt name len
        $this->settings['optNameLen'] = IntHelper::getMin($nameLen, $maxLen);
        return $fmtOpts;
    }

    /****************************************************************
     * getter/setter methods
     ***************************************************************/

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
     */
    public function setDesc(string $desc): void
    {
        $this->desc = $desc;
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
     * @return string
     */
    public function popFirstRawArg(): string
    {
        return array_shift($this->rawArgs);
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
    public function isStrictCheckArgs(): bool
    {
        return $this->strictCheckArgs;
    }

    /**
     * @param bool $strictCheckArgs
     */
    public function setStrictCheckArgs(bool $strictCheckArgs): void
    {
        $this->strictCheckArgs = $strictCheckArgs;
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
        return $this->scriptName;
    }

    /**
     * @param string $scriptName
     */
    public function setScriptName(string $scriptName): void
    {
        $this->scriptName = $scriptName;
    }

    /**
     * @return bool
     */
    public function isAutoRenderHelp(): bool
    {
        return $this->autoRenderHelp;
    }

    /**
     * @param bool $autoRenderHelp
     */
    public function setAutoRenderHelp(bool $autoRenderHelp): void
    {
        $this->autoRenderHelp = $autoRenderHelp;
    }

    /**
     * @return bool
     */
    public function isShowTypeOnHelp(): bool
    {
        return $this->showTypeOnHelp;
    }

    /**
     * @param bool $showTypeOnHelp
     */
    public function setShowTypeOnHelp(bool $showTypeOnHelp): void
    {
        $this->showTypeOnHelp = $showTypeOnHelp;
    }
}
