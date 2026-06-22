<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

/**
 * Glossary helper methods.
 */
trait GlossaryTermTrait {

  /**
   * Builds a summary for a glossary term.
   *
   * @param string|null $definition
   *   The definition text.
   * @param string|null $summary
   *   The summary text.
   * @param string|null $format
   *   The format to use for the summary.
   *
   * @return string
   *   The summary text.
   */
  protected function buildSummary(?string $definition, ?string $summary, ?string $format): string {
    $summary ??= '';
    $summary = trim($summary);
    if (!empty($summary)) {
      return $summary;
    }

    // If no summary is set, fallback to a shortened version of the definition.
    $definition ??= '';
    return text_summary(trim(strip_tags($definition)), $format, 300);
  }

}
