<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag;

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
        AutoConfigTrait::__construct as supper;
    }

    public string $name = '';

    public string $desc = 'command description';

    /**
     * @var array<string, string|array>
     */
    public array $options = [];

    /**
     * @var array<string, string|array>
     */
    public array $arguments = [];

    /**
     * @var FlagsParser
     */
    private FlagsParser $flags;

    /**
     * @var callable(FlagsParser): mixed
     */
    private $handler;

    /**
     * @param callable(self): void $fn
     *
     * @return $this
     */
    public static function newWith(callable $fn): self
    {
        return (new self)->config($fn);
    }

    /**
     * Class constructor.
     *
     * @param array $config
     */
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
     * @return mixed
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
