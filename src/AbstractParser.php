<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Contract\ParserInterface;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\Obj\Traits\NameAliasTrait;
use Toolkit\Stdlib\Obj\Traits\QuickInitTrait;
use function explode;
use function strpos;

/**
 * class AbstractParser
 */
abstract class AbstractParser implements ParserInterface
{
    use QuickInitTrait;
    use NameAliasTrait;

    /**
     * @var bool
     */
    protected $parsed = false;

    /**
     * The raw input flags
     *
     * @var array
     */
    protected $rawFlags = [];

    /**
     * The remaining raw args, after option parsed from {@see $rawFlags}
     *
     * @var array
     */
    protected $rawArgs = [];

    /**
     * The required option names.
     *
     * @var array
     */
    protected $requiredOpts = [];

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
     * Class constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        Obj::init($this, $config);
    }

    /**
     * @return array
     */
    protected function parseRawArgs(): array
    {
        $args = [];

        // parse arguments
        foreach ($this->rawArgs as $arg) {
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
     * @return array
     */
    public function getRequiredOpts(): array
    {
        return $this->requiredOpts;
    }

    /**
     * @return array
     */
    public function getRawArgs(): array
    {
        return $this->rawArgs;
    }

    /**
     * @return array
     */
    public function getRawFlags(): array
    {
        return $this->rawFlags;
    }

    /**
     * @return bool
     */
    public function isParsed(): bool
    {
        return $this->parsed;
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
}
