<?php

namespace Wexample\SymfonyTesting\Traits\Parsing;

trait InlineJsonVarExtractorTrait
{
    /**
     * Extract a JSON value assigned to a JS variable inside $content.
     * Matches patterns like:
     *   window.appRegistry.layoutRenderData = {...};
     *   layoutRenderData = [...];
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    protected function extractInlineJsonAssignment(string $content, string $variableName): array
    {
        $match = [];
        if (!preg_match('/\\b'.preg_quote($variableName, '/').'\\b\\s*=\\s*/', $content, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $length = strlen($content);

        while ($start < $length && ctype_space($content[$start])) {
            $start++;
        }

        if ($start >= $length) {
            return [];
        }

        $json = $this->extractJsValue($content, $start);

        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractJsValue(string $content, int $start): ?string
    {
        $first = $content[$start] ?? null;
        if ($first === null) {
            return null;
        }

        if ($first === '{' || $first === '[') {
            return $this->extractBalancedJson($content, $start);
        }

        $end = strpos($content, ';', $start);
        if ($end === false) {
            $end = strlen($content);
        }

        return trim(substr($content, $start, $end - $start));
    }

    private function extractBalancedJson(string $content, int $start): ?string
    {
        $open = $content[$start];
        $close = $open === '{' ? '}' : ']';

        $depth = 0;
        $inString = false;
        $escape = false;
        $quote = '';

        $length = strlen($content);
        for ($i = $start; $i < $length; $i++) {
            $ch = $content[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($ch === '\\\\') {
                    $escape = true;
                    continue;
                }

                if ($ch === $quote) {
                    $inString = false;
                    $quote = '';
                }

                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $quote = $ch;
                continue;
            }

            if ($ch === $open) {
                $depth++;
                continue;
            }

            if ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}

