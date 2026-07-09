<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt\Tmgmt;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Prevents tmgmt from translating social-media links.
 *
 * The field holds only machine values (a platform key and an account handle),
 * so nothing in it should be translated.
 */
final class SocialMediaFieldProcessor extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    foreach (Element::children($data) as $delta) {
      foreach (Element::children($data[$delta]) as $property) {
        if (isset($data[$delta][$property]['#translate'])) {
          $data[$delta][$property]['#translate'] = FALSE;
        }
      }
    }

    return $data;
  }

}
