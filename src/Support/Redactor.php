<?php

namespace PNScripts\SentfoldLite\Support;

use InvalidArgumentException;

/**
 * Applies the configured pattern => replacement pairs and counts the hits.
 */
class Redactor
{
    /** @var array<string, string> */
    private array $patterns;

    private int $count = 0;

    /**
     * @param  array<string, string>  $patterns
     */
    public function __construct(array $patterns)
    {
        foreach ($patterns as $pattern => $replacement) {
            if (@preg_match($pattern, '') === false) {
                throw new InvalidArgumentException("Sentfold: invalid redaction pattern {$pattern}");
            }
        }
        $this->patterns = $patterns;
    }

    public function apply(?string $value): ?string
    {
        if ($value === null || $value === '' || $this->patterns === []) {
            return $value;
        }
        foreach ($this->patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value, -1, $hits) ?? $value;
            $this->count += $hits;
        }

        return $value;
    }

    /**
     * Number of replacements made since this instance was created.
     */
    public function count(): int
    {
        return $this->count;
    }
}
