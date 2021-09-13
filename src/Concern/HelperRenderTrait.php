<?php declare(strict_types=1);

namespace Toolkit\PFlag\Concern;

use Toolkit\Cli\Color\ColorTag;
use Toolkit\PFlag\AbstractFlags;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\FlagUtil;
use Toolkit\Stdlib\Helper\DataHelper;
use Toolkit\Stdlib\Helper\IntHelper;
use Toolkit\Stdlib\Str;
use function array_shift;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_object;
use function ksort;
use function method_exists;
use function sprintf;
use function strlen;
use function strpos;
use function trim;

/**
 * trait HelperRenderTrait
 */
trait HelperRenderTrait
{

    /**
     * Custom help renderer.
     *
     * @var callable
     */
    protected $helpRenderer;

    /**
     * Auto render help on provide '-h', '--help'
     *
     * @var bool
     */
    protected $autoRenderHelp = true;

    // -------------------- settings for built-in render help --------------------

    /**
     * @var string|array|null
     */
    protected $moreHelp = '';

    /**
     * @var string|array|null
     */
    protected $exampleHelp = '';

    /**
     * Show flag data type on render help
     *
     * @var bool
     */
    protected $showTypeOnHelp = true;

    /**
     * @var callable
     */
    private $beforePrintHelp;

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

        // --------------- extra: moreHelp, example -----------------
        if ($this->exampleHelp) {
            $buf->writeln("\n<ylw>Examples:</ylw>");

            $lines = is_array($this->exampleHelp) ? $this->exampleHelp : [$this->exampleHelp];
            $buf->writeln('  ' . implode("\n  ", $lines));;
        }

        if ($this->moreHelp) {
            $buf->writeln("\n<ylw>More Help:</ylw>");

            $lines = is_array($this->moreHelp) ? $this->moreHelp : [$this->moreHelp];
            $buf->writeln('  ' . implode("\n  ", $lines));
        }

        // fire event
        if ($fn = $this->beforePrintHelp) {
            $text = $fn($buf->getAndClear());
        } else {
            $text = $buf->getAndClear();
        }

        return $withColor ? $text : ColorTag::clear($text);
    }

    /**
     * @param array|Option|Argument $define
     *
     * @return array
     * @see AbstractFlags::DEFINE_ITEM for array $define
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

    /**
     * @return array|string|null
     */
    public function getMoreHelp()
    {
        return $this->moreHelp;
    }

    /**
     * @param array|string|null $moreHelp
     */
    public function setMoreHelp($moreHelp): void
    {
        $this->moreHelp = $moreHelp;
    }

    /**
     * @return array|string|null
     */
    public function getExampleHelp()
    {
        return $this->exampleHelp;
    }

    /**
     * @param array|string|null $example
     */
    public function setExample($example): void
    {
        $this->setExampleHelp($example);
    }

    /**
     * @param array|string|null $exampleHelp
     */
    public function setExampleHelp($exampleHelp): void
    {
        $this->exampleHelp = $exampleHelp;
    }

    /**
     * @param callable $beforePrintHelp
     */
    public function setBeforePrintHelp(callable $beforePrintHelp): void
    {
        $this->beforePrintHelp = $beforePrintHelp;
    }
}
