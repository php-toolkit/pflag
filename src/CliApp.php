<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;
use Throwable;
use Toolkit\Cli\Cli;
use Toolkit\Cli\Color;
use Toolkit\Stdlib\Arr;
use Toolkit\Stdlib\Str;
use function array_merge;
use function array_shift;
use function basename;
use function class_exists;
use function function_exists;
use function getcwd;
use function implode;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function ksort;
use function method_exists;
use function rtrim;
use function str_pad;
use function strlen;
use function strpos;
use function ucfirst;
use function vdump;

/**
 * class CliApp
 *
 * @author inhere
 */
class CliApp
{
    /** @var self|null */
    public static $global;

    private const COMMAND_CONFIG = [
        'desc'      => '',
        'usage'     => '',
        'help'      => '',
        'options'   => [],
        'arguments' => [],
    ];

    /** @var string Current dir */
    private $pwd;

    /**
     * @var array
     */
    protected $params = [
        'name'    => 'My application',
        'desc'    => 'My command line application',
        'version' => '0.2.1'
    ];

    /**
     * @var FlagsParser
     */
    protected $flags;

    /**
     * @var FlagsParser|null
     */
    protected $cmdFlags;

    /**
     * @var string
     */
    private $scriptFile = '';

    /**
     * @var string
     */
    private $scriptName = '';

    /**
     * Current run command
     *
     * @var string
     */
    private $command = '';

    /**
     * User add commands handlers
     *
     * @var array<string, callable>
     */
    private $commands = [];

    /**
     * Command messages for the commands
     *
     * ```php
     * [
     *  command1 => [
     *     see COMMAND_CONFIG
     *  ],
     * ]
     * ```
     *
     * @var array
     * @see COMMAND_CONFIG
     */
    private $metadata = [];

    /**
     * @var int
     */
    private $keyWidth = 10;

    /**
     * @return static
     */
    public static function global(): self
    {
        if (!self::$global) {
            self::$global = new self();
        }

        return self::$global;
    }

    /**
     * @param CliApp $global
     */
    public static function setGlobal(self $global): void
    {
        self::$global = $global;
    }

    /**
     * Class constructor.
     *
     * @param array{flags: array} $config
     */
    public function __construct(array $config = [])
    {
        // get current dir
        $this->pwd = (string)getcwd();

        $fsConf = [];
        if ($config) {
            $fsConf = Arr::remove($config, 'flags', []);
            $this->setParams($config);
        }

        $this->flags = new SFlags($fsConf);
        $this->flags->setAutoBindArgs(false);
    }

    /**
     * @param FlagsParser $fs
     */
    protected function beforeRun(FlagsParser $fs): void
    {
        $desc = ucfirst($this->params['desc']);
        if ($ver = $this->params['version']) {
            $desc .= "(<red>v$ver</red>)";
        }

        $fs->setDesc($desc);
        // $fs->setStopOnFistArg(true);
        $fs->addOptsByRules([
            'h, help' => 'bool;display application help'
        ]);
    }

    /**
     * @param bool $exit
     */
    public function run(bool $exit = false): void
    {
        // parse cli args
        $args = $_SERVER['argv'];

        // get script file
        $scriptFile = array_shift($args);
        $this->setScriptFile($scriptFile);

        $this->runByArgs($args, $exit);
    }

    /**
     * @param string[] $args
     * @param bool $exit
     */
    public function runByArgs(array $args, bool $exit = false): void
    {
        $this->beforeRun($this->flags);

        // parse global flags
        if (!$this->flags->parse($args)) {
            $this->displayCommands();
            return;
        }

        $args = $this->flags->getRemainArgs();

        // find command.
        if (isset($args[0]) && $args[0]) {
            $fArg = $args[0]; // check first argument.
            if ($fArg[0] !== '-') {
                $this->command = array_shift($args);
            }
        }

        $this->dispatch($args, $exit);
    }

    /**
     * @param array $args
     * @param bool $exit
     */
    public function dispatch(array $args, bool $exit = true): void
    {
        if (!$command = $this->command) {
            $this->flags->displayHelp();
            $this->displayCommands();
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->displayCommands("The command '$command' is not exists!");
            return;
        }

        $status = $this->doHandle($args);

        if ($exit) {
            $this->stop($status);
        }
    }

    /**
     * @param int $code
     */
    #[NoReturn]
    public function stop(int $code = 0): void
    {
        exit($code);
    }

    /**
     * @param array $args
     *
     * @return int
     */
    protected function doHandle(array $args): int
    {
        $command = $this->command;
        $handler = $this->commands[$command];
        $cFlags  = $this->initCommandFlags($command, $handler);

        try {
            // false - on render help.
            if (!$cFlags->parse($args)) {
                return 0;
            }

            $status = $this->runHandler($handler, $cFlags);
        } catch (Throwable $e) {
            $status = static::handleException($e);
        }

        return (int)$status;
    }

    /**
     * @param string $command
     * @param $handler
     *
     * @return FlagsParser
     */
    protected function initCommandFlags(string $command, $handler): FlagsParser
    {
        $cFlags = SFlags::new();
        $config = $this->metadata[$command] ?? [];

        $cFlags->setDesc($config['desc']);
        if (!empty($config['help'])) {
            $cFlags->setHelp($config['help']);
        }

        $cFlags->addOptsByRules($config['options'] ?? []);
        $cFlags->addArgsByRules($config['arguments'] ?? []);

        // has config method
        if (is_object($handler) && method_exists($handler, 'configure')) {
            $handler->configure($cFlags);
        }

        return $cFlags;
    }

    /**
     * @param mixed $handler
     * @param FlagsParser $cFlags
     *
     * @return mixed
     */
    public function runHandler(mixed $handler, FlagsParser $cFlags): mixed
    {
        // function name
        if (is_string($handler) && function_exists($handler)) {
            return $handler($cFlags, $this);
        }

        if (is_object($handler)) {
            // call $handler->execute()
            if (method_exists($handler, 'execute')) {
                return $handler->execute($cFlags, $this);
            }

            // call \Closure OR $handler->__invoke()
            if (method_exists($handler, '__invoke')) {
                return $handler($cFlags, $this);
            }
        }

        throw new RuntimeException("Invalid handler of the command: $this->command");
    }

    /**
     * @param Throwable $e
     *
     * @return int
     */
    public static function handleException(Throwable $e): int
    {
        if ($e instanceof InvalidArgumentException) {
            Color::println('ERROR: ' . $e->getMessage(), 'error');
            return 0;
        }

        $code = $e->getCode() !== 0 ? $e->getCode() : -1;
        $eTpl = "Exception(%d): <red>%s</red>\nFile: %s(Line %d)\n\nError Trace:\n%s\n";

        // print exception message
        Color::printf($eTpl, $code, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());

        return $code;
    }

    /**
     * alias of addCommand()
     *
     * @param string $command
     * @param callable $handler
     * @param array{desc:string,options:array,arguments:array} $config
     */
    public function add(string $command, callable $handler, array $config = []): void
    {
        $this->addCommand($command, $handler, $config);
    }

    /**
     * @param string $command
     * @param callable|class-string|object $handler
     * @param array{desc:string,options:array,arguments:array} $config
     */
    public function addCommand(string $command, $handler, array $config = []): void
    {
        if (!$command) {
            throw new InvalidArgumentException('command name can not be empty');
        }

        if (($len = strlen($command)) > $this->keyWidth) {
            $this->keyWidth = $len;
        }

        // class string.
        if (is_string($handler) && class_exists($handler)) {
            $handler = new $handler;
        }

        if (is_callable($handler)) {
            $this->commands[$command] = $handler;
        } elseif (is_object($handler) && method_exists($handler, 'configure')) {
            $this->commands[$command] = $handler;
        } else {
            throw new InvalidArgumentException("invalid command handler of '$command'");
        }

        if (!$config) {
            $config = ['desc' => "no config for command '$command'"];
        }

        $this->metadata[$command] = array_merge(self::COMMAND_CONFIG, $config);
    }

    /**
     * @param array $commands
     *
     * @throws InvalidArgumentException
     */
    public function addCommands(array $commands): void
    {
        foreach ($commands as $command => $handler) {
            $conf = [];
            $name = is_string($command) ? $command : '';

            if (is_array($handler) && isset($handler['handler'])) {
                $conf = $handler;
                $name = $conf['name'] ?? $name;

                $handler = $conf['handler'];
                unset($conf['name'], $conf['handler']);
            }

            $this->addCommand($name, $handler, $conf);
        }
    }

    /****************************************************************************
     * helper methods
     ****************************************************************************/

    /**
     * display commands list
     *
     * @param string $err
     */
    public function displayCommands(string $err = ''): void
    {
        if ($err) {
            Cli::println("<red>ERROR</red>: $err\n");
        }

        $script = $this->scriptName;

        $help = "<comment>Commands:</comment>\n";
        $data = $this->metadata;
        ksort($data);

        // $globalOptions = $this->flags->getOptsHelpLines();
        // Cli::println($globalOptions);

        foreach ($data as $command => $item) {
            $command = str_pad($command, $this->keyWidth);

            $desc = $item['desc'] ? ucfirst($item['desc']) : 'No description for the command';
            $help .= "  <green>$command</green>   $desc\n";
        }

        $help .= "\nFor command usage please run: <cyan>$script COMMAND -h</cyan>";

        Cli::println($help);
    }

    /**
     * @param string $name
     */
    public function displayCommandHelp(string $name): void
    {
        $checkVar = false;
        $fullCmd  = $this->scriptFile . " $name";

        $config = $this->metadata[$name] ?? [];
        $usage  = "$fullCmd [args ...] [--opts ...]";

        if (!$config) {
            $nodes = [
                'No description for the command',
                "<comment>Usage:</comment> \n  $usage"
            ];
        } else {
            $checkVar = true;
            $userHelp = rtrim($config['help'], "\n");

            $usage = $config['usage'] ?: $usage;
            $nodes = [
                ucfirst($config['desc']),
                "<comment>Usage:</comment> \n  $usage\n",
                $userHelp ? $userHelp . "\n" : ''
            ];
        }

        $help = implode("\n", $nodes);

        if ($checkVar && strpos($help, '{{')) {
            $vars = [
                'command' => $name,
                'fullCmd' => $fullCmd,
                'workDir' => $this->pwd,
                'pwdDir'  => $this->pwd,
                'script'  => $this->scriptFile,
            ];

            $help = Str::renderTemplate($help, $vars, '${%s}');
        }

        Cli::println($help);
    }

    /****************************************************************************
     * getter/setter methods
     ****************************************************************************/

    /**
     * @return string
     */
    public function getScriptFile(): string
    {
        return $this->scriptFile;
    }

    /**
     * @return string
     */
    public function getScriptName(): string
    {
        return basename($this->scriptFile);
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
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string $command
     */
    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    /**
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @param array $commands
     */
    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param int $keyWidth
     */
    public function setKeyWidth(int $keyWidth): void
    {
        $this->keyWidth = $keyWidth > 1 ? $keyWidth : 12;
    }

    /**
     * @return string
     */
    public function getPwd(): string
    {
        return $this->pwd;
    }

    /**
     * @param array $params
     *
     * @deprecated please use setParams()
     */
    public function setMetas(array $params): void
    {
        $this->setParams($params);
    }

    /**
     * @param string $key
     * @param null   $default
     *
     * @return mixed
     */
    public function getParam(string $key, $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed  $val
     */
    public function setParam(string $key, mixed $val): void
    {
        $this->params[$key] = $val;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params): void
    {
        $this->params = array_merge($this->params, $params);
    }

    /**
     * @return FlagsParser
     */
    public function getFlags(): FlagsParser
    {
        return $this->flags;
    }
}
