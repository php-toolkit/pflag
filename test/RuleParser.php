<?php declare(strict_types=1);

namespace Toolkit\PFlagTest;

use Toolkit\PFlag\Concern\RuleParserTrait;

/**
 * class RuleParser
 */
class RuleParser
{
    use RuleParserTrait;

    public static function new(): self
    {
        return new self();
    }

    /**
     * @param array|string $rule
     * @param string       $name
     * @param int          $index
     * @param bool         $isOption
     *
     * @return array
     */
    public function parse($rule, string $name = '', int $index = 0, bool $isOption = true): array
    {
        return $this->parseRule($rule, $name, $index, $isOption);
    }

    /**
     * @param array|string $rule
     * @param string       $name
     * @param int          $index
     *
     * @return array
     */
    public function parseArg($rule, string $name = '', int $index = 0): array
    {
        return $this->parseRule($rule, $name, $index, false);
    }

    /**
     * @param array|string $rule
     * @param string       $name
     *
     * @return array
     */
    public function parseOpt($rule, string $name): array
    {
        return $this->parseRule($rule, $name, 0, true);
    }
}