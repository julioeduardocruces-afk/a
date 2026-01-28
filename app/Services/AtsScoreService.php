<?php

namespace App\Services;

class AtsScoreService
{
    /**
     * Compute heuristic ATS score 0-100 from optimized CV text and metadata.
     */
    public function score(
        string $plainText,
        string $targetRole,
        string $targetIndustry,
        array $atsKeywords = [],
    ): array {
        $scores = [
            'headline_match' => $this->scoreHeadlineMatch($plainText, $targetRole),
            'keyword_density' => $this->scoreKeywordDensity($plainText, $atsKeywords),
            'sections_present' => $this->scoreSections($plainText),
            'length' => $this->scoreLength($plainText),
            'structure' => $this->scoreStructure($plainText),
        ];

        $weights = [
            'headline_match' => 0.25,
            'keyword_density' => 0.25,
            'sections_present' => 0.20,
            'length' => 0.15,
            'structure' => 0.15,
        ];

        $overall = 0;
        foreach ($scores as $key => $value) {
            $overall += $value * ($weights[$key] ?? 0);
        }

        $scores['overall'] = (int)round($overall);

        return $scores;
    }

    private function scoreHeadlineMatch(string $text, string $targetRole): int
    {
        $roleLower = mb_strtolower($targetRole);
        $lines = array_slice(explode("\n", $text), 0, 10);
        $headerText = mb_strtolower(implode(' ', $lines));

        // Check if target role appears in first 10 lines (headline/summary area)
        if (str_contains($headerText, $roleLower)) {
            return 100;
        }

        // Partial match: check individual words
        $roleWords = preg_split('/\s+/', $roleLower);
        $found = 0;
        foreach ($roleWords as $word) {
            if (mb_strlen($word) > 2 && str_contains($headerText, $word)) {
                $found++;
            }
        }

        return count($roleWords) > 0
            ? (int)round(($found / count($roleWords)) * 80)
            : 0;
    }

    private function scoreKeywordDensity(string $text, array $keywords): int
    {
        if (empty($keywords)) {
            return 50; // neutral if no keywords
        }

        $textLower = mb_strtolower($text);
        $found = 0;

        foreach ($keywords as $kw) {
            if (str_contains($textLower, mb_strtolower($kw))) {
                $found++;
            }
        }

        $ratio = $found / count($keywords);

        return match (true) {
            $ratio >= 0.8 => 100,
            $ratio >= 0.6 => 80,
            $ratio >= 0.4 => 60,
            $ratio >= 0.2 => 40,
            default       => 20,
        };
    }

    private function scoreSections(string $text): int
    {
        $requiredSections = [
            '/resumen|summary|perfil|profile|objetivo/iu',
            '/experiencia|experience|work/iu',
            '/educaci[oó]n|education|formaci[oó]n/iu',
            '/habilidades|skills|competencias/iu',
        ];

        $optionalSections = [
            '/certificaci|certification|cursos/iu',
            '/idiomas|languages/iu',
        ];

        $score = 0;
        foreach ($requiredSections as $pattern) {
            if (preg_match($pattern, $text)) {
                $score += 20;
            }
        }
        foreach ($optionalSections as $pattern) {
            if (preg_match($pattern, $text)) {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    private function scoreLength(string $text): int
    {
        $wordCount = str_word_count($text);

        // Ideal: 400-800 words (1-2 pages)
        return match (true) {
            $wordCount < 200   => 30,
            $wordCount < 400   => 60,
            $wordCount <= 800  => 100,
            $wordCount <= 1200 => 80,
            default            => 50,
        };
    }

    private function scoreStructure(string $text): int
    {
        $score = 100;

        // Penalize if tables detected (| separators in multiple lines)
        $tableLines = preg_match_all('/\|.*\|/', $text);
        if ($tableLines > 2) {
            $score -= 30;
        }

        // Penalize excessive special characters (icons/emojis)
        if (preg_match('/[\x{1F600}-\x{1F9FF}]/u', $text)) {
            $score -= 20;
        }

        // Penalize very short lines in sequence (column layout hint)
        $lines = explode("\n", $text);
        $shortLineSequence = 0;
        foreach ($lines as $line) {
            if (mb_strlen(trim($line)) > 0 && mb_strlen(trim($line)) < 20) {
                $shortLineSequence++;
            } else {
                $shortLineSequence = 0;
            }
            if ($shortLineSequence > 5) {
                $score -= 15;
                break;
            }
        }

        return max(0, $score);
    }
}
