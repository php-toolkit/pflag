<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Traits;

/**
 * Trait FlagParsingTrait
 * @package Toolkit\PFlag\Traits
 */
trait FlagParsingTrait
{
    /**
     * @var bool
     */
    private $parsed = false;

    /**
     * Special short style
     *  gnu: `-abc` will expand: `-a -b -c`
     *  posix: `-abc`  will expand: `-a=bc`
     *
     * @var string
     */
    private $shortStyle = 'posix';

    /**
     * Whether stop parse option on first argument
     *
     * @var bool
     */
    private $stopOnFistArg = true;

    private $errOnUndefined = false;

    // private $stopOnUndefined = false;
    private $skipUndefined = false;

    private $ignoreUnknown = false;

    /**
     * The raw input flags
     *
     * @var array
     */
    protected $rawFlags = [];

    /**
     * The remaining args.
     * After on option parsed from {@see $rawFlags}
     *
     * @var array
     */
    protected $rawArgs = [];

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
