<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Tmgmt;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Prevents tmgmt from translating iframe machine properties.
 */
final class IframeFieldProcessor extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    // Only the iframe title should be translated; machine names/settings are
    // excluded.
    foreach (Element::children($data) as $delta) {
      foreach (Element::children($data[$delta]) as $property) {
        if ($property !== 'title' && isset($data[$delta][$property]['#translate'])) {
          $data[$delta][$property]['#translate'] = FALSE;
        }
      }
    }

    return $data;
  }

}
