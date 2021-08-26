<?php declare(strict_types=1);
/**
 * This file is part of toolkit/pflag.
 *
 * @link     https://github.com/php-toolkit
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace Toolkit\PFlag\Flag;

use Toolkit\Cli\Helper\FlagHelper;
use Toolkit\PFlag\Exception\FlagException;
use Toolkit\PFlag\FlagType;
use Toolkit\Stdlib\Str;
use function array_filter;
use function array_map;
use function array_unshift;
use function implode;
use function strlen;

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
    public function isBoolean(): bool
    {
        return $this->type === FlagType::BOOL;
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
        if (!$alias) {
            return;
        }

        if (!FlagHelper::isValidName($alias)) {
            throw new FlagException('invalid option alias: ' . $alias);
        }

        if (strlen($alias) < 2) {
            throw new FlagException('flag option alias length cannot be < 2 ');
        }

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
     * @param string $shortcut eg: 'a,b' Or '-a,-b' Or '-a, -b'
     */
    public function setShortcut(string $shortcut): void
    {
        $shortcuts = preg_split('{,\s?-?}', ltrim($shortcut, '-'));

        $this->setShorts(array_filter($shortcuts));
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
        if ($shorts) {
            $this->shorts   = $shorts;
            $this->shortcut = '-' . implode(', -', $shorts);
        }
    }

    /**
     * @param bool $forHelp
     *
     * @return string
     */
    public function getDesc(bool $forHelp = false): string
    {
        $desc = $this->desc;
        if ($forHelp) {
            $desc = $desc ? Str::ucfirst($desc) : 'Option ' . $this->name;
        }

        return $desc;
    }

    /**
     * @return string
     */
    public function getHelpName(): string
    {
        $longs = [];
        if ($this->alias) {
            $longs[] = $this->alias;
        }

        $longs[] = $this->name;

        // prepend '--'
        $nodes = array_map(static function (string $name) {
            return (strlen($name) > 1 ? '--' : '-') . $name;
        }, $longs);

        if ($this->shortcut) {
            array_unshift($nodes, $this->shortcut);
        }

        return implode(', ', $nodes);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $info = parent::toArray();

        $info['alias']  = $this->alias;
        $info['shorts'] = $this->shorts;
        return $info;
    }
}
