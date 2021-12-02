<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;
use Throwable;
use Toolkit\Cli\Cli;
use Toolkit\Cli\Color;
use function array_merge;
use function array_shift;
use function array_values;
use function basename;
use function class_exists;
use function function_exists;
use function getcwd;
use function implode;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function ksort;
use function method_exists;
use function printf;
use function rtrim;
use function str_pad;
use function strlen;
use function strpos;
use function trim;
use function ucfirst;

/**
 * class CliApp
 *
 * @author inhere
 */
class CliApp
{
    /** @var self|null */
    public static ?self $global = null;

    private const COMMAND_CONFIG = [
        'desc'  => '',
        'usage' => '',
        'help'  => '',
    ];

    /** @var string Current dir */
    private string $pwd;

    /**
     * @var array
     */
    protected array $params = [
        'name'    => 'My application',
        'desc'    => 'My command line application',
        'version' => '0.2.1'
    ];

    /**
     * @var array Parsed from `arg0 name=val var2=val2`
     */
    private array $args = [];

    /**
     * @var array Parsed from `--name=val --var2=val2 -d`
     */
    private mixed $opts;

    /**
     * @var string
     */
    private string $scriptFile = '';

    /**
     * @var string
     */
    private string $command = '';

    /**
     * @var array User add commands
     */
    private array $commands = [];

    /**
     * @var array Command messages for the commands
     */
    private array $messages = [];

    /**
     * @var int
     */
    private int $keyWidth = 12;

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
     * @param array      $config
     */
    public function __construct(array $config = [])
    {
        // get current dir
        $this->pwd = (string)getcwd();

        if ($config) {
            $this->setParams($config);
        }
    }

    /**
     * @param array|null $args
     * @param bool $exit
     */
    public function run(array $args = null, bool $exit = false): void
    {
        // parse cli args
        if ($args === null) {
            $args = $_SERVER['argv'];

            // get script file
            $this->scriptFile = array_shift($args);
        }

        // parse flags
        [
            $this->args,
            $this->opts
        ] = Flags::parseArgv(array_values($args), ['mergeOpts' => true]);

        $this->findCommand();

        $this->dispatch($exit);
    }

    /**
     * find command name. it is first argument.
     */
    protected function findCommand(): void
    {
        if (!isset($this->args[0])) {
            return;
        }

        $newArgs = [];
        foreach ($this->args as $key => $value) {
            if ($key === 0) {
                $this->command = trim($value);
            } elseif (is_int($key)) {
                $newArgs[] = $value;
            } else {
                $newArgs[$key] = $value;
            }
        }

        $this->args = $newArgs;
    }

    /**
     * @param bool $exit
     *
     * @throws InvalidArgumentException
     */
    public function dispatch(bool $exit = true): void
    {
        $status = $this->doHandle();

        if ($exit) {
            $this->stop($status);
        }
    }

    /**
     * @return int
     */
    protected function doHandle(): int
    {
        if (!$command = $this->command) {
            $this->displayHelp();
            return 0;
        }

        if (!isset($this->commands[$command])) {
            $this->displayHelp("The command '$command' is not exists!");
            return 0;
        }

        if (isset($this->opts['h']) || isset($this->opts['help'])) {
            $this->displayCommandHelp($command);
            return 0;
        }

        try {
            $status = $this->runHandler($command, $this->commands[$command]);
        } catch (Throwable $e) {
            $status = $this->handleException($e);
        }

        return (int)$status;
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
     * @param string $command
     * @param mixed  $handler
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function runHandler(string $command, mixed $handler): mixed
    {
        if (is_string($handler)) {
            // function name
            if (function_exists($handler)) {
                return $handler($this);
            }

            if (class_exists($handler)) {
                $handler = new $handler;

                // $handler->execute()
                if (method_exists($handler, 'execute')) {
                    return $handler->execute($this);
                }
            }
        }

        // a \Closure OR $handler->__invoke()
        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return $handler($this);
        }

        throw new RuntimeException("Invalid handler of the command: $command");
    }

    /**
     * @param Throwable $e
     *
     * @return int
     */
    protected function handleException(Throwable $e): int
    {
        if ($e instanceof InvalidArgumentException) {
            Color::println('ERROR: ' . $e->getMessage(), 'error');
            return 0;
        }

        $code = $e->getCode() !== 0 ? $e->getCode() : -1;
        $eTpl = "Exception(%d): %s\nFile: %s(Line %d)\nTrace:\n%s\n";

        // print exception message
        printf($eTpl, $code, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());

        return $code;
    }

    /**
     * @param callable $handler
     * @param array    $config
     */
    public function addObject(callable $handler, array $config = []): void
    {
        if (is_object($handler) && method_exists($handler, '__invoke')) {
            // has config method
            if (method_exists($handler, 'getHelpConfig')) {
                $config = $handler->getHelpConfig();
            }

            $this->addByConfig($handler, $config);
            return;
        }

        throw new InvalidArgumentException('Command handler must be an object and has method: __invoke');
    }

    /**
     * @param callable $handler
     * @param array    $config
     */
    public function addByConfig(callable $handler, array $config): void
    {
        if (empty($config['name'])) {
            throw new InvalidArgumentException('Invalid arguments for add command');
        }

        $this->addCommand($config['name'], $handler, $config);
    }

    /**
     * @param string            $command
     * @param callable          $handler
     * @param array|string|null $config
     */
    public function add(string $command, callable $handler, array|string $config = null): void
    {
        $this->addCommand($command, $handler, $config);
    }

    /**
     * @param string            $command
     * @param callable          $handler
     * @param array|string|null $config
     */
    public function addCommand(string $command, callable $handler, array|string $config = null): void
    {
        if (!$command) {
            throw new InvalidArgumentException('Invalid arguments for add command');
        }

        if (($len = strlen($command)) > $this->keyWidth) {
            $this->keyWidth = $len;
        }

        $this->commands[$command] = $handler;

        // no config
        if (!$config) {
            return;
        }

        if (is_string($config)) {
            $desc   = trim($config);
            $config = self::COMMAND_CONFIG;

            // append desc
            $config['desc'] = $desc;

            // save
            $this->messages[$command] = $config;
        } elseif (is_array($config)) {
            $this->messages[$command] = array_merge(self::COMMAND_CONFIG, $config);
        }
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
     * @param string $err
     */
    public function displayHelp(string $err = ''): void
    {
        if ($err) {
            Cli::println("<red>ERROR</red>: $err\n");
        }

        // help
        $desc = ucfirst($this->params['desc']);
        if ($ver = $this->params['version']) {
            $desc .= "(<red>v$ver</red>)";
        }

        $script = $this->scriptFile;
        $usage  = "<cyan>$script COMMAND -h</cyan>";

        $help = "$desc\n\n<comment>Usage:</comment> $usage\n<comment>Commands:</comment>\n";
        $data = $this->messages;
        ksort($data);

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

        $config = $this->messages[$name] ?? [];
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
            $help = strtr($help, [
                '{{command}}' => $name,
                '{{fullCmd}}' => $fullCmd,
                '{{workDir}}' => $this->pwd,
                '{{pwdDir}}'  => $this->pwd,
                '{{script}}'  => $this->scriptFile,
            ]);
        }

        Cli::println($help);
    }

    /**
     * @param int|string $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getArg(int|string $name, mixed $default = null): mixed
    {
        return $this->args[$name] ?? $default;
    }

    /**
     * @param int|string $name
     * @param int        $default
     *
     * @return int
     */
    public function getIntArg(int|string $name, int $default = 0): int
    {
        return (int)$this->getArg($name, $default);
    }

    /**
     * @param int|string $name
     * @param string     $default
     *
     * @return string
     */
    public function getStrArg(int|string $name, string $default = ''): string
    {
        return (string)$this->getArg($name, $default);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getOpt(string $name, mixed $default = null): mixed
    {
        return $this->opts[$name] ?? $default;
    }

    /**
     * @param string $name
     * @param int    $default
     *
     * @return int
     */
    public function getIntOpt(string $name, int $default = 0): int
    {
        return (int)$this->getOpt($name, $default);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getStrOpt(string $name, string $default = ''): string
    {
        return (string)$this->getOpt($name, $default);
    }

    /**
     * @param string $name
     * @param bool   $default
     *
     * @return bool
     */
    public function getBoolOpt(string $name, bool $default = false): bool
    {
        return (bool)$this->getOpt($name, $default);
    }

    /****************************************************************************
     * getter/setter methods
     ****************************************************************************/

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param array $args
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    /**
     * @return array
     */
    public function getOpts(): array
    {
        return $this->opts;
    }

    /**
     * @param array $opts
     */
    public function setOpts(array $opts): void
    {
        $this->opts = $opts;
    }

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
        $this->scriptFile = $scriptFile;
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
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return int
     */
    public function getKeyWidth(): int
    {
        return $this->keyWidth;
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
     * @return array
     * @deprecated please use getParams();
     */
    public function getMetas(): array
    {
        return $this->getParams();
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
     * @return mixed|string|null
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
}
