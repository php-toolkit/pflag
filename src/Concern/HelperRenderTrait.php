<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Concern;

use Toolkit\Cli\Color\ColorTag;
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagsParser;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\FlagUtil;
use Toolkit\Stdlib\Helper\DataHelper;
use Toolkit\Stdlib\Helper\IntHelper;
use Toolkit\Stdlib\Str;
use function array_merge;
use function array_push;
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
use function ucfirst;

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
     * @var string|array|null
     */
    protected string|array|null $moreHelp = '';

    /**
     * @var string|array|null
     */
    protected string|array|null $exampleHelp = '';

    // -------------------- settings for built-in render help --------------------

    /**
     * Auto render help on provide '-h', '--help'
     *
     * @var bool
     */
    protected bool $autoRenderHelp = true;

    /**
     * Show flag data type on render help.
     *
     * if False:
     *
     * -o, --opt    Option desc
     *
     * if True:
     *
     * -o, --opt STRING   Option desc
     *
     * @var bool
     */
    protected bool $showTypeOnHelp = true;

    /**
     * @var bool
     */
    protected bool $showHiddenOpt = false;

    /**
     * Will call it on before print help message
     *
     * @var callable(string): string
     */
    private $beforePrintHelp;

    /**
     * @param array $argDefines
     * @param array $optDefines
     * @param bool  $withColor
     * @param bool  $hasShortOpt
     *
     * @return string
     */
    protected function doBuildHelp(array $argDefines, array $optDefines, bool $withColor, bool $hasShortOpt = false): string
    {
        $buf = Str\StrBuffer::new();

        // ------- desc -------
        if ($title = $this->desc) {
            $buf->writeln(Str::ucfirst($title) . "\n");
        }

        $hasArgs = count($argDefines) > 0;
        $hasOpts = count($optDefines) > 0;

        // ------- usage -------
        $binName = $this->getScriptName();
        if ($hasArgs || $hasOpts) {
            $buf->writeln("<ylw>Usage:</ylw> $binName [--Options ...] [Arguments ...]\n");
        }

        // ------- opts -------
        if ($hasOpts) {
            $buf->writeln('<ylw>Options:</ylw>');
        }

        $nameTag = 'info';
        $fmtOpts = $this->buildOptsForHelp($optDefines, $hasShortOpt);

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
                    $buf->writef("      %s%s\n", $indent, $line);
                }
            }
        }

        $hasOpts && $buf->writeln('');

        // ------- args -------
        // $nameTag = 'info';
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
                    $buf->writef("      %s%s\n", $indent, $line);
                }
            }
        }

        // --------------- extra: moreHelp, example -----------------
        if ($this->exampleHelp) {
            $buf->writeln("\n<ylw>Examples:</ylw>");

            $lines = is_array($this->exampleHelp) ? $this->exampleHelp : [$this->exampleHelp];
            $buf->writeln('  ' . implode("\n  ", $lines));
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
     * @param array|Argument|Option $define
     *
     * @return array
     * @see FlagsParser::DEFINE_ITEM for array $define
     */
    protected function formatDesc(Argument|Option|array $define): array
    {
        $desc = $define['desc'] ?: 'No description';
        if ($define['required']) {
            $desc = '<red1>*</red1>' . $desc;
        }

        // validator limit
        if (!empty($define['validator'])) {
            /** @see ValidatorInterface */
            $v = $define['validator'];

            if (is_object($v) && method_exists($v, '__toString')) {
                $limit = (string)$v;
                $desc  .= $limit ? "\n" . $limit : '';
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

        // $this->settings['argNameLen'] = $maxLen;
        $this->set('argNameLen', $maxLen);
        return $fmtArgs;
    }

    /**
     * @param array $optDefines
     * @param bool  $hasShortOpt
     *
     * @return array
     */
    protected function buildOptsForHelp(array $optDefines, bool $hasShortOpt): array
    {
        if (!$optDefines) {
            return [];
        }

        $fmtOpts = [];
        $nameLen = $this->settings['optNameLen'];
        ksort($optDefines);

        // $hasShortOpt=true will add `strlen('-h, ')` indent.
        $prefix = $hasShortOpt ? '    ' : '';

        /** @var array|Option $opt {@see FlagsParser::DEFINE_ITEM} */
        foreach ($optDefines as $name => $opt) {
            // hidden option
            if ($this->showHiddenOpt === false && $opt['hidden']) {
                continue;
            }

            $names = $opt['shorts'];
            // support multi alias names.
            if (isset($opt['aliases']) && $opt['aliases']) {
                array_push($names, ...$opt['aliases']);
            }

            // option name.
            $names[] = $name;
            // option description
            $desc = $opt['desc'] ? trim($opt['desc']) : '';

            // ensure desc is not empty
            $opt['desc'] = $desc ? Str::ucfirst($desc) : "Option $name";
            $helpName    = FlagUtil::buildOptHelpName($names);

            // first elem is long option name.
            if (isset($names[0][1])) {
                $helpName = $prefix . $helpName;
            }

            // show type name.
            if ($this->showTypeOnHelp) {
                $typeName = $opt['helpType'] ?: FlagType::getHelpName($opt['type']);
                $helpName .= $typeName ? " $typeName" : '';
            }

            $nameLen = IntHelper::getMax($nameLen, strlen($helpName));
            // append
            $fmtOpts[$helpName] = $opt;
        }

        // limit option name width
        $maxLen = IntHelper::getMax($this->settings['descNlOnOptLen'], self::OPT_MAX_WIDTH);

        // $this->settings['descNlOnOptLen'] = $maxLen;
        $this->set('descNlOnOptLen', $maxLen);
        // set opt name len
        // $this->settings['optNameLen'] = IntHelper::getMin($nameLen, $maxLen);
        $this->set('optNameLen', IntHelper::getMin($nameLen, $maxLen));
        return $fmtOpts;
    }

    /**
     * @param string $name
     * @param array $opt
     *
     * @return array<string, string>
     */
    protected function buildOptHelpLine(string $name, array $opt): array
    {
        $names = $opt['shorts'];
        // has aliases
        if ($opt['aliases']) {
            $names = array_merge($names, $opt['aliases']);
        }

        $names[]  = $name;
        $helpName = FlagUtil::buildOptHelpName($names);

        // show type name.
        if ($this->showTypeOnHelp) {
            $typeName = $opt['helpType'] ?: FlagType::getHelpName($opt['type']);
            $helpName .= $typeName ? " $typeName" : '';
        }

        $opt['desc'] = $opt['desc'] ? ucfirst($opt['desc']) : "Option $name";

        // format desc
        [$desc, $otherLines] = $this->formatDesc($opt);
        if ($otherLines) {
            $desc .= "\n" . implode("\n", $otherLines);
        }

        return [$helpName, $desc];
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
    public function getMoreHelp(): array|string|null
    {
        return $this->moreHelp;
    }

    /**
     * @param array|string|null $moreHelp
     */
    public function setHelp(array|string|null $moreHelp): void
    {
        $this->setMoreHelp($moreHelp);
    }

    /**
     * @param array|string|null $moreHelp
     */
    public function setMoreHelp(array|string|null $moreHelp): void
    {
        if ($moreHelp) {
            $this->moreHelp = $moreHelp;
        }
    }

    /**
     * @return array|string|null
     */
    public function getExampleHelp(): array|string|null
    {
        return $this->exampleHelp;
    }

    /**
     * @param array|string|null $example
     */
    public function setExample(array|string|null $example): void
    {
        $this->setExampleHelp($example);
    }

    /**
     * @param array|string|null $exampleHelp
     */
    public function setExampleHelp(array|string|null $exampleHelp): void
    {
        if ($exampleHelp) {
            $this->exampleHelp = $exampleHelp;
        }
    }

    /**
     * @param callable(string): string $beforePrintHelp
     */
    public function setBeforePrintHelp(callable $beforePrintHelp): void
    {
        $this->beforePrintHelp = $beforePrintHelp;
    }

    /**
     * @param bool $showHiddenOpt
     */
    public function setShowHiddenOpt(bool $showHiddenOpt): void
    {
        $this->showHiddenOpt = $showHiddenOpt;
    }
}
