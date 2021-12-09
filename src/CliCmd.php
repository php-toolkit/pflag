<?php declare(strict_types=1);

namespace Toolkit\PFlag;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Toolkit\Stdlib\Obj\Traits\AutoConfigTrait;

/**
 * class CliCmd
 *
 * @author inhere
 */
class CliCmd
{
    use AutoConfigTrait {
        __construct as supper;
    }

    public string $name = '';
    public string $desc = 'command description';

    public array $options = [];
    public array $arguments = [];

    /**
     * @var FlagsParser
     */
    private FlagsParser|SFlags $flags;

    /**
     * @var callable(FlagsParser): mixed
     */
    private $handler;

    public function __construct(array $config = [])
    {
        $this->supper($config);

        $this->flags = new SFlags();
    }

    /**
     * @param callable(self): void $fn
     *
     * @return $this
     */
    public function config(callable $fn): self
    {
        $fn($this);
        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function withHandler(callable $handler): self
    {
        return $this->setHandler($handler);
    }

    /**
     * @param array $options
     *
     * @return $this
     */
    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param array $arguments
     *
     * @return $this
     */
    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @param FlagsParser $fs
     */
    protected function prepare(FlagsParser $fs): void
    {
        $fs->setName($this->name);
        $fs->setDesc($this->desc);

        $options = $this->options;
        if (!isset($options['help'])) {
            $options['help'] = 'bool;display command help;;;h';
        }

        $fs->addOptsByRules($options);
        $fs->addArgsByRules($this->arguments);
    }

    /**
     * @return int|mixed
     */
    public function run(): mixed
    {
        $handler = $this->handler;
        if (!$handler) {
            throw new RuntimeException('command handler must be set before run.');
        }

        $this->prepare($this->flags);

        try {
            if (!$this->flags->parse()) {
                return 0;
            }

            return $handler($this->flags);
        } catch (Throwable $e) {
            CliApp::handleException($e);
        }

        return -1;
    }

    /**
     * @param callable $handler
     *
     * @return CliCmd
     */
    public function setHandler(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * @return FlagsParser
     */
    public function getFlags(): FlagsParser
    {
        return $this->flags;
    }
}
