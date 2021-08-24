<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use function implode;

/**
 * Class Option
 * - definition a input option
 *
 * @package Toolkit\PFlag\Flag
 */
class Option extends AbstractFlag
{
    /**
     * alias name
     *
     * @var string
     */
    private $alias = '';

    /**
     * Shortcuts of the option. eg: ['a', 'b']
     *
     * @var array
     */
    private $shorts = [];

    /**
     * Shortcuts of the option, string format. eg: 'a|b'
     *
     * @var string
     */
    private $shortcut = '';

    /**
     * @return bool
     */
    public function isArray(): bool
    {
        return $this->hasMode(self::OPT_IS_ARRAY);
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->hasMode(self::OPT_OPTIONAL);
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->hasMode(self::OPT_REQUIRED);
    }

    /**
     * @return bool
     */
    public function isBoolean(): bool
    {
        return $this->hasMode(self::OPT_BOOLEAN);
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @param string $alias
     */
    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * @return string
     */
    public function getShortcut(): string
    {
        return $this->shortcut;
    }

    /**
     * @param string $shortcut eg: 'a|b'
     */
    public function setShortcut(string $shortcut): void
    {
        $shortcuts = preg_split('{(\|)-?}', ltrim($shortcut, '-'));
        $shortcuts = array_filter($shortcuts);

        $this->setShorts($shortcuts);
    }

    /**
     * @return array
     */
    public function getShorts(): array
    {
        return $this->shorts;
    }

    /**
     * @param array $shorts
     */
    public function setShorts(array $shorts): void
    {
        $this->shorts   = $shorts;
        $this->shortcut = implode('|', $shorts);
    }
}
