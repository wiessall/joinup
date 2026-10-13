<?php

namespace Drupal\ai_output_checks\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

final class RegexpChecker {

  private ?array $compiled = NULL;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly string $configName = 'ai_output_checks.settings',
    private readonly string $configKey = 'forbidden_pattern_map',
  ) {}

  /**
   * Compile config map reason => [phrases...] into reason => regex.
   * We escape phrases so they are treated literally.
   *
   * Equivalent-ish to your Python:
   *   (^|[^A-Za-z])(?:phrase1|phrase2|...)([^A-Za-z]|$)
   */
  private function compile(): array {
    if ($this->compiled !== NULL) {
      return $this->compiled;
    }

    $config = $this->configFactory->get($this->configName);
    $map = $config->get($this->configKey) ?? [];

    if (!is_array($map) || !$map) {
      return $this->compiled = [];
    }

    $out = [];

    foreach ($map as $reason => $phrases) {
      if (!is_string($reason) || $reason === '') {
        continue;
      }
      if (!is_array($phrases) || !$phrases) {
        continue;
      }

      // Escape each phrase for safe literal matching.
      $escaped = [];
      foreach ($phrases as $p) {
        if (!is_string($p) || $p === '') {
          continue;
        }
        $escaped[] = preg_quote($p, '/');
      }

      if (!$escaped) {
        continue;
      }

      // Join with alternation.
      $alt = implode('|', $escaped);

      // Your Python boundary idea: not a letter before/after.
      // Use Unicode letters: \p{L}
      $regex = '/(^|[^\p{L}])(?:' . $alt . ')([^\p{L}]|$)/iu';

      $out[$reason] = $regex;
    }

    return $this->compiled = $out;
  }

  /**
   * Check text and return hits.
   * Empty array means OK.
   *
   * @return array<int, array{reason:string, pattern:string, match:string}>
   */
  public function check(string $text): array {
    $text = trim($text);
    if ($text === '') {
      return [];
    }

    $compiled = $this->compile();
    if (!$compiled) {
      return [];
    }

    $hits = [];

    foreach ($compiled as $reason => $regex) {
      $ok = @preg_match($regex, $text, $m);
      if ($ok === FALSE) {
        \Drupal::logger('ai_output_checks')->warning(
          'RegexpChecker: invalid compiled regex for reason @reason: @re',
          ['@reason' => $reason, '@re' => $regex]
        );
        continue;
      }

      if ($ok === 1) {
        // $m[0] includes boundary chars; keep it for debugging,
        // but you can also provide a "core" match if you want.
        $hits[] = [
          'reason' => $reason,
          'pattern' => $regex,
          'match' => $m[0] ?? '',
        ];
      }
    }

    return $hits;
  }

  /**
   * Optional helper if you want to use the compiled map elsewhere.
   */
  public function getCompiledMap(): array {
    return $this->compile();
  }

}
