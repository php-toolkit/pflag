<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use InvalidArgumentException;
use Toolkit\Cli\Cli;
use Toolkit\Cli\Color\ColorTag;
use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Contract\ParserInterface;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\Stdlib\Arr;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\Obj\Traits\NameAliasTrait;
use Toolkit\Stdlib\Obj\Traits\QuickInitTrait;
use Toolkit\Stdlib\Str;
use function array_merge;
use function basename;
use function count;
use function explode;
use function is_array;
use function is_callable;
use function ksort;
use function strlen;
use function strpos;
use function trim;
use function vdump;

/**
 * class AbstractParser
 */
abstract class AbstractParser implements ParserInterface
{
    use QuickInitTrait;
    use NameAliasTrait;

    protected const TRIM_CHARS = "; \t\n\r\0\x0B";

    public const RULE_SEP = ';';

    public const STATUS_OK = 0;

    public const STATUS_ERR = 1;

    public const STATUS_HELP = 2; // found `-h|--help` flag

    public const SHORT_STYLE_GUN = 'gnu';

    public const SHORT_STYLE_POSIX = 'posix';

    public const DEFINE_ITEM = [
        'name'      => '',
        'desc'      => '',
        'type'      => FlagType::STRING,
        'showType'  => '', // use for show help
        // 'index'    => 0, // only for argument
        'required'  => false,
        'default'   => null,
        'shorts'    => [], // only for option. ['a', 'b']
        // value validator
        'validator' => null,
        // 'category' => null
    ];

    /**
     * @var bool
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
        'hasShorts'  => false,
        'argNameLen' => 12,
        'optNameLen' => 12,
    ];

    // -------------------- settings for parse --------------------

    /**
     * Special short style
     *  gnu: `-abc` will expand: `-a -b -c`
     *  posix: `-abc`  will expand: `-a=bc`
     *
     * @var string
     */
    protected $shortStyle = 'posix';

    /**
     * Whether stop parse option on first argument
     *
     * @var bool
     */
    protected $stopOnFistArg = true;

    protected $errOnUndefined = false;

    /**
     * Whether stop parse option on found undefined option
     *
     * @var bool
     */
    protected $stopOnUndefined = true;

    protected $skipUndefined = false;

    protected $ignoreUnknown = false;

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
            $buf->writeln(Str::ucfirst($title));
            $buf->writeln('');
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

        $maxLen = $this->settings['argNameLen'];
        foreach ($fmtArgs as $hName => $arg) {
            $desc  = $arg['desc'] ? Str::ucfirst($arg['desc']) : 'Argument arg' . $arg['index'];
            $hName = Str::padRight($hName, $maxLen);

            if ($arg['required']) {
                $desc = '<red1>*</red1>' . $desc;
            }

            $buf->writef("  <%s>%s</%s>    %s\n", $nameTag, $hName, $nameTag, $desc);
        }
        $buf->writeln('');

        // ------- opts -------
        if ($hasOpts) {
            $buf->writeln('<ylw>Options:</ylw>');
        }

        $nameTag = 'info';
        $fmtOpts = $this->buildOptsForHelp($optDefines);

        $maxLen = $this->settings['optNameLen'];
        foreach ($fmtOpts as $hName => $opt) {
            $desc  = $opt['desc'] ? Str::ucfirst($opt['desc']) : 'Option ' . $opt['name'];
            $hName = Str::padRight($hName, $maxLen);

            if ($opt['required']) {
                $desc = '<red1>*</red1>' . $desc;
            }

            $buf->writef("  <%s>%s</%s>   %s\n", $nameTag, $hName, $nameTag, $desc);
        }

        return $withColor ? $buf->clear() : ColorTag::clear($buf->clear());
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

        /** @var array $arg {@see DEFINE_ITEM} */
        foreach ($argDefines as $arg) {
            $helpName = $arg['name'] ?: 'arg' . $arg['index'];

            $type = $arg['type'];
            if (FlagType::isArray($type)) {
                $helpName .= '...';
            }

            if ($this->showTypeOnHelp) {
                $typeName = FlagType::getHelpName($type);
                $helpName .= $typeName ? " $typeName" : '';
            }

            $maxLen = FlagUtil::getMaxInt($maxLen, strlen($helpName));
            // unset($arg['validator']);

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
        $maxLen  = $this->settings['optNameLen'];
        ksort($optDefines);

        /** @var array $opt {@see DEFINE_ITEM} */
        foreach ($optDefines as $name => $opt) {
            $names   = $opt['shorts'];
            $names[] = $name;

            $helpName = FlagUtil::buildOptHelpName($names);
            if ($this->showTypeOnHelp) {
                $typeName = FlagType::getHelpName($opt['type']);
                $helpName .= $typeName ? " $typeName" : '';
            }

            $maxLen = FlagUtil::getMaxInt($maxLen, strlen($helpName));
            // append
            $fmtOpts[$helpName] = $opt;
        }

        $this->settings['optNameLen'] = $maxLen;
        return $fmtOpts;
    }

    /****************************************************************
     * parse rule to definition
     ***************************************************************/

    /**
     * Parse rule
     *
     * **array rule**
     *
     * - will merge an {@see DEFINE_ITEM}
     *
     * **string rule**
     *
     * - full rule. (format: 'type;required;default;desc')
     * - rule item position is fixed.
     * - if ignore `type`, will use default type: string.
     *
     * can ignore item use empty:
     * - 'type' - only set type.
     * - 'type;;;desc' - not set required,default
     *
     * @param string|array $rule
     * @param string       $name
     * @param int          $index
     * @param bool         $isOption
     *
     * @return array {@see DEFINE_ITEM}
     * @see $argRules
     * @see $optRules
     */
    protected function parseRule($rule, string $name = '', int $index = 0, bool $isOption = true): array
    {
        $shortsFromArr = [];
        if (is_array($rule)) {
            $item = Arr::replace(self::DEFINE_ITEM, $rule);
            // set alias by array item
            $shortsFromArr = $item['shorts'];
        } else { // parse string rule.
            $item = self::DEFINE_ITEM;
            $rule = trim((string)$rule, self::TRIM_CHARS);

            if (strpos($rule, self::RULE_SEP) === false) {
                $item['type'] = $rule;
            } else { // eg: 'type;required;default;desc'
                $nodes = Str::splitTrimmed($rule, self::RULE_SEP, 4);

                // first is type.
                $item['type'] = $nodes[0];
                // second is required
                $item['required'] = false;
                if ($nodes[1] && ($nodes[1] === 'required' || Str::toBool($nodes[1]))) {
                    $item['required'] = true;
                }

                // more: default, desc
                if (isset($nodes[2]) && $nodes[2] !== '') {
                    $item['default'] = $nodes[2];
                }
                if (!empty($nodes[3])) {
                    $item['desc'] = $nodes[3];
                }
            }
        }

        $name = $name ?: $item['name'];
        if ($isOption) {
            // parse option name.
            [$name, $shorts] = $this->parseRuleOptName($name);

            // save alias
            $item['shorts'] = $shorts ?: $shortsFromArr;
            if ($item['required']) {
                $this->requiredOpts[] = $name;
            }
        } else {
            $item['index'] = $index;
        }

        $nameMark = $name ? "(name: $name)" : "(#$index)";

        // check type
        if (!FlagType::isValid($type = $item['type'])) {
            throw new FlagException("cannot define invalid flag type: $type$nameMark");
        }

        // validator must be callable
        if (!empty($item['validator']) && !is_callable($item['validator'])) {
            throw new InvalidArgumentException("validator must be callable. flag: $nameMark");
        }

        $item['name'] = $name;
        return $item;
    }

    /**
     * Parse option name and shorts
     *
     * @param string $key 'lang,s' => option name is 'lang', alias 's'
     *
     * @return array [name, shorts]
     */
    protected function parseRuleOptName(string $key): array
    {
        $key = trim($key, self::TRIM_CHARS);
        if (!$key) {
            throw new FlagException('flag option name cannot be empty');
        }

        // only name.
        if (strpos($key, ',') === false) {
            return [$key, []];
        }

        $name = '';
        $keys = Str::explode($key, ',');

        // TIP: first is the option name. remaining is shorts.
        $shorts = [];
        foreach ($keys as $i => $k) {
            if ($i === 0) {
                $name = $k;
            } else {
                $shorts[] = $k;
            }
        }

        return [$name, $shorts];
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
