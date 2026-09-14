<?php

namespace Drupal\ai_output_checks\Service;

use FuzzyWuzzy\Fuzz;
class DuplicateDetector {

    protected Fuzz $fuzz;
    protected int $minSharedChars = 7;
    protected float $minLenRatio = 0.65;
    protected float $substringLenRatio = 0.55;
    protected float $minTokenJaccard = 0.5;
    protected float $minOverlapShorter = 0.70;
    protected int $minFuzzyScore = 85;

    public function __construct(Fuzz $fuzz) {
        $this->fuzz = $fuzz;
    }

    public function partialRatio(string $a, string $b): float {
        $na = $this->normalize($a);
        $nb = $this->normalize($b);

        if ($na === '' || $nb === '') {
            return 0.0;
        }

        return $this->fuzz->partialRatio($na, $nb);
    }

    public function normalize(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        // 1) Decompose accents: "é" => "é" (e + combining accent).
        if (class_exists('\Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
        }

        // 2) Strip combining marks (accents).
        // \p{Mn} = "Mark, nonspacing" (combining diacritics).
        $text = preg_replace('/\p{Mn}+/u', '', $text);

        // 3) Keep only letters/digits/spaces.
        $text = preg_replace('/[^a-z0-9 ]+/u', ' ', $text);

        // 4) Collapse whitespace.
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Returns [shared_char_count, overlap_ratio].
     *
     * overlap_ratio is |A∩B| / |A∪B| over unique characters.
     */
    public function charOverlap(string $a, string $b): array {
        $setA = array_unique(str_split($a));
        $setB = array_unique(str_split($b));

        $intersection = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));

        $sharedCount = count($intersection);
        $overlap = count($union) > 0 ? $sharedCount / count($union) : 0.0;

        return [$sharedCount, $overlap];
    }

    public function tokenJaccard(string $a, string $b): float {
        $setA = array_unique(array_filter(explode(' ', $a), 'strlen'));
        $setB = array_unique(array_filter(explode(' ', $b), 'strlen'));

        $intersection = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    public function shorterOverlap(string $a, string $b): float {
        // counts multiset overlap of characters
        $countA = array_count_values(str_split($a));
        $countB = array_count_values(str_split($b));
        $shared = 0;
        foreach ($countA as $char => $count) {
            $shared += min($count, $countB[$char] ?? 0);
        }
        $shorterLen = min(strlen($a), strlen($b));
        return $shorterLen > 0 ? $shared / $shorterLen : 0.0;
    }

    /**
     * Return bool by default; array with metrics if $details = true.
     */
    public function isDuplicate(string $s1, string $s2, bool $details = false): array|bool {
        $a = $this->normalize($s1);
        $b = $this->normalize($s2);

        // substring check on normalized strings
        $substring = str_contains($a, $b) || str_contains($b, $a);

        // NOTE: now charOverlap returns [sharedCount, ratio]
        [$sharedChars, $charOverlap] = $this->charOverlap($a, $b);

        $lenRatio = min(strlen($a), strlen($b)) / max(strlen($a), strlen($b));
        $jaccard = $this->tokenJaccard($a, $b);
        $shorter = $this->shorterOverlap($a, $b);

        $decisions = [
            'shared_chars' => $sharedChars >= $this->minSharedChars,
            'length_ratio' => $substring
              ? $lenRatio >= $this->substringLenRatio
              : $lenRatio >= $this->minLenRatio,
            'token_jaccard' => $jaccard >= $this->minTokenJaccard,
            'shorter_overlap' => $shorter >= $this->minOverlapShorter,
        ];

        $trueCount = 0;
        foreach ($decisions as $flag) {
            if ($flag) {
                $trueCount++;
            }
        }
        $duplicate = $trueCount > 2;

        if (!$details) {
            return $duplicate;
        }

        return [
            'duplicate' => $duplicate,
            'a' => $a,
            'b' => $b,
            'metrics' => [
                'shared_chars' => $sharedChars,
                'char_overlap' => $charOverlap,
                'length_ratio' => $lenRatio,
                'token_jaccard' => $jaccard,
                'shorter_overlap' => $shorter,
            ],
            'decisions' => $decisions,
        ];
    }
}
