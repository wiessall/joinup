<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

/**
 * Pure functions for comparing organisation names. No Drupal dependency.
 */
final class NameMatcher {

  private const STOPWORDS = [
    'the', 'of', 'and', 'for', 'in', 'on', 'a', 'an', 'at', 'to', 'de', 'del', 'la', 'las', 'los', 'el', 'y', 'e',
    'et', 'du', 'des', 'le', 'les', 'da', 'do', 'dos', 'das', 'und', 'der', 'die', 'fur',
  ];

  private const LEGAL_SUFFIXES = [
    'ltd', 'limited', 'inc', 'incorporated', 'llc', 'llp', 'plc', 'pvt', 'pty', 'gmbh', 'ag', 'sa', 'sas', 'sarl',
    'srl', 'spa', 'bv', 'nv', 'co', 'corp', 'corporation', 'company', 'private', 'npc', 'pt',
  ];

  /**
   * Spelling variants folded to one form.
   */
  private const VARIANTS = [
    'centre' => 'center', 'programme' => 'program', 'organisation' => 'organization', 'agricultural' => 'agriculture',
    'agricole' => 'agriculture', 'agricola' => 'agriculture', 'labour' => 'labor', 'univ' => 'university',
    'govt' => 'government', 'dept' => 'department', 'intl' => 'international',
  ];

  /**
   * Lowercase ASCII, any script transliterated, punctuation to spaces.
   */
  public static function fold(string $text): string {
    static $translit = NULL;
    $translit ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
    $text = str_replace('&', ' and ', $text);
    $text = $translit ? (string) $translit->transliterate($text) : mb_strtolower($text);
    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
  }

  /**
   * Comparison key: folded, without stopwords and legal suffixes.
   */
  public static function key(string $text): string {
    $drop = array_flip([...self::STOPWORDS, ...self::LEGAL_SUFFIXES]);
    $tokens = [];
    foreach (explode(' ', self::fold($text)) as $t) {
      // Single letters are leftovers of apostrophes and initials: "People's" -> "people s".
      if ($t === '' || isset($drop[$t]) || (strlen($t) === 1 && !ctype_digit($t))) {
        continue;
      }
      $t = self::VARIANTS[$t] ?? $t;
      // Plural to singular, roughly: "sciences" -> "science", leaving "business", "campus", "analysis".
      if (strlen($t) > 4 && str_ends_with($t, 's') && !preg_match('/(ss|us|is)$/', $t)) {
        $t = substr($t, 0, -1);
      }
      $tokens[] = $t;
    }
    return implode(' ', $tokens);
  }

  /**
   * Splits trailing qualifiers off a name: "Makerere University (Uganda)" -> ["Makerere University", ["uganda"]].
   *
   * Acronyms in brackets are not qualifiers; splitAcronym() handles those.
   *
   * @return array{0: string, 1: list<string>}
   */
  public static function splitQualifiers(string $name): array {
    $qualifiers = [];
    while (preg_match('/^(.+?)\s*[(\[]([^()\[\]]{2,40})[)\]]\s*$/u', $name, $m) && !self::looksLikeAcronym($m[2])) {
      array_unshift($qualifiers, self::fold($m[2]));
      $name = trim($m[1]);
    }
    return [$name, $qualifiers];
  }

  /**
   * Upper-case initials of the significant words: "World Food Programme" -> "WFP".
   */
  public static function initials(string $text): string {
    $words = array_filter(explode(' ', self::key($text)), fn($t) => !ctype_digit($t));
    return strtoupper(implode('', array_map(fn($t) => $t[0], $words)));
  }

  /**
   * Upper-case letters and digits only: "Cropin" -> "CROPIN", "UN-Habitat" -> "UNHABITAT".
   */
  public static function acronymKey(string $text): string {
    return strtoupper(str_replace(' ', '', self::fold($text)));
  }

  /**
   * Splits "Name (ACR)", "Name [ACR]" or "Name - ACR" into name and acronym.
   *
   * @return array{0: string, 1: ?string}
   */
  public static function splitAcronym(string $title): array {
    $title = trim((string) preg_replace('/\s+/', ' ', $title));
    // "Name (ACR)", also followed by qualifiers: "Name (ACR) (Kenya)", "Name [ACR], Govt. of India".
    if (preg_match('/^(.+?)\s*[(\[]\s*([^()\[\]]{2,20}?)\s*[)\]]\s*(.*)$/u', $title, $m)
      && self::looksLikeAcronym($m[2])) {
      $rest = trim($m[3], " ,;-");
      return [trim($m[1], " ,;-") . ($rest !== '' ? ' (' . trim($rest, '()[] ') . ')' : ''), $m[2]];
    }
    if (preg_match('/^(.{6,}?)\s+[-–]\s+(\S{2,12})$/u', $title, $m) && self::looksLikeAcronym($m[2])) {
      return [trim($m[1]), $m[2]];
    }
    return [$title, NULL];
  }

  /**
   * First word with two capitals, at most three words, no legal form: "ACRE", "AfricaRice", "SADERA Tz";
   * not "Sri Lanka" or "Pty".
   */
  public static function looksLikeAcronym(string $text): bool {
    $words = preg_split('/\s+/', trim($text)) ?: [];
    if (count($words) > 3 || in_array(self::fold($text), self::LEGAL_SUFFIXES, TRUE)) {
      return FALSE;
    }
    return preg_match_all('/\p{Lu}/u', $words[0]) >= 2
      && preg_match('/^\p{Lu}[\p{L}\d&.\-]*(\s+[\p{L}\d.]+){0,2}$/u', $text) === 1;
  }

  /**
   * Words that say what kind of body something is, not which one.
   */
  private const GENERIC = [
    'ministry', 'department', 'government', 'office', 'directorate', 'division', 'unit', 'faculty', 'school',
    'agency', 'bureau', 'board', 'commission', 'council', 'authority', 'service', 'national', 'federal', 'state',
    'regional', 'provincial', 'district', 'local', 'public', 'general', 'agriculture', 'research', 'forest',
    'forestry', 'fishery', 'fisherie', 'livestock', 'environment', 'environmental', 'rural', 'development', 'food',
    'water', 'soil', 'land', 'wildlife', 'park', 'resource', 'extension', 'affair', 'farmer', 'association',
    'center', 'institute',
  ];

  /**
   * Whether a key is made only of generic words ("ministry agriculture", "forest department").
   */
  public static function isGeneric(string $key): bool {
    return $key !== '' && !array_diff(explode(' ', $key), self::GENERIC);
  }

  /**
   * Share of the words in $keyA that have a close counterpart (same or nearly the same word) in $keyB.
   */
  public static function coverage(string $keyA, string $keyB): float {
    $a = explode(' ', $keyA);
    $b = explode(' ', $keyB);
    $hit = 0;
    foreach ($a as $t) {
      foreach ($b as $u) {
        if ($t === $u || 1 - levenshtein($t, $u) / max(strlen($t), strlen($u)) >= 0.8) {
          $hit++;
          break;
        }
      }
    }
    return $hit / count($a);
  }

  /**
   * Similarity of two keys, 0..1: edit distance on the sorted tokens, so word order does not count.
   */
  public static function similarity(string $keyA, string $keyB): float {
    if ($keyA === '' || $keyB === '') {
      return 0.0;
    }
    $sortA = explode(' ', $keyA);
    $sortB = explode(' ', $keyB);
    sort($sortA);
    sort($sortB);
    $a = implode(' ', $sortA);
    $b = implode(' ', $sortB);
    $max = max(strlen($a), strlen($b));
    return 1.0 - levenshtein($a, $b) / $max;
  }

}
