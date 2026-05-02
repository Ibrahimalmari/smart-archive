<?php

namespace App\Http\Services\Document;

class OcrTextNormalizer
{
    public function normalize(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $text = $this->stripUtf8Bom($text);
        $text = $this->repairCommonMojibake($text);
        $text = $this->normalizeWhitespace($text);
        $text = $this->normalizeMixedScriptSpacing($text);
        $text = $this->normalizePunctuation($text);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized) && $normalized !== '') {
                $text = $normalized;
            }
        }

        return trim($text);
    }

    public function direction(?string $text): string
    {
        $text = (string) $text;

        preg_match_all('/\p{Arabic}/u', $text, $arabicMatches);
        preg_match_all('/[A-Za-z]/u', $text, $latinMatches);

        $arabicCount = count($arabicMatches[0]);
        $latinCount = count($latinMatches[0]);

        if ($arabicCount === 0 && $latinCount === 0) {
            return 'auto';
        }

        return $arabicCount >= $latinCount ? 'rtl' : 'ltr';
    }

    public function formatForDisplay(?string $text): string
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return '';
        }

        $lines = preg_split("/\n/u", $text) ?: [];
        $formatted = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $formatted[] = '';
                continue;
            }

            $formatted[] = $this->wrapLineForDirection($line);
        }

        return implode("\n", $formatted);
    }

    private function stripUtf8Bom(string $text): string
    {
        return preg_replace('/^\xEF\xBB\xBF/u', '', $text) ?? $text;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return $text;
    }

    private function normalizeMixedScriptSpacing(string $text): string
    {
        $patterns = [
            '/(\p{Arabic})([A-Za-z0-9])/u' => '$1 $2',
            '/([A-Za-z0-9])(\p{Arabic})/u' => '$1 $2',
            '/(\p{Arabic})([:()\/\-]+)/u' => '$1 $2',
            '/([:()\/\-]+)(\p{Arabic})/u' => '$1 $2',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    }

    private function normalizePunctuation(string $text): string
    {
        $text = str_replace(['“', '”', '„'], '"', $text);
        $text = str_replace(['’', '‘', '`'], "'", $text);
        $text = str_replace(['–', '—'], '-', $text);
        $text = str_replace(['﴾', '﴿'], ['(', ')'], $text);

        $text = preg_replace('/\s*([،؛:,.!?()\/-])\s*/u', ' $1 ', $text) ?? $text;
        $text = preg_replace('/\(\s+/u', '(', $text) ?? $text;
        $text = preg_replace('/\s+\)/u', ')', $text) ?? $text;

        return preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    }

    private function repairCommonMojibake(string $text): string
    {
        if (!$this->looksLikeMojibake($text)) {
            return $text;
        }

        $bestCandidate = $text;
        $bestScore = $this->readabilityScore($text);

        foreach (['Windows-1252', 'ISO-8859-1'] as $sourceEncoding) {
            $candidate = @mb_convert_encoding($text, $sourceEncoding, 'UTF-8');
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $candidateScore = $this->readabilityScore($candidate);
            if ($candidateScore > $bestScore) {
                $bestCandidate = $candidate;
                $bestScore = $candidateScore;
            }
        }

        return $bestCandidate;
    }

    private function looksLikeMojibake(string $text): bool
    {
        return preg_match('/[\x{00D8}\x{00D9}\x{00DA}\x{00DB}\x{00C3}\x{00D0}\x{00D1}]/u', $text) === 1;
    }

    private function readabilityScore(string $text): int
    {
        preg_match_all('/\p{Arabic}/u', $text, $arabicMatches);
        $arabicCount = count($arabicMatches[0]);

        preg_match_all('/[\x{00D8}\x{00D9}\x{00DA}\x{00DB}\x{00C3}\x{00D0}\x{00D1}]/u', $text, $garbledMatches);
        $garbledCount = count($garbledMatches[0]);

        preg_match_all('/[A-Za-z0-9]/u', $text, $latinMatches);
        $latinCount = count($latinMatches[0]);

        return ($arabicCount * 4) + $latinCount - ($garbledCount * 3);
    }

    private function wrapLineForDirection(string $line): string
    {
        return match ($this->direction($line)) {
            'rtl' => "\u{202B}{$line}\u{202C}",
            'ltr' => "\u{202A}{$line}\u{202C}",
            default => $line,
        };
    }
}
