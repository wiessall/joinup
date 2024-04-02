<?php

declare(strict_types=1);

namespace Drupal\joinup_slick;

/**
 * Static routines for converting fields to Slick carousels.
 *
 * @internal
 */
final class JoinupSlick {

  /**
   * Adds template suggestions to the given slick theme function.
   *
   * @param array $suggestions
   *   The predefined template suggestions (if any).
   * @param array $variables
   *   The variables made available for the template.
   */
  public static function addSlickTemplateSuggestion(array &$suggestions, array $variables): void {
    $optionsetId = $variables['element']['#settings']['optionset'];
    $suggestions[] = $base = "{$variables['theme_hook_original']}__$optionsetId";
    $joinupSlick = $variables['element']['#settings']['joinup_slick'] ?? NULL;
    if (is_array($joinupSlick)) {
      [
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fieldName,
      ] = $joinupSlick;
      $suggestions[] = implode('__', [
        $base,
        $entityType,
        $bundle,
        $fieldName,
      ]);
    }
  }

}
