<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt\Tmgmt;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Prevents tmgmt from translating block plugin IDs.
 */
final class BlockFieldProcessor extends DefaultFieldProcessor {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    foreach (Element::children($data) as $delta) {
      if (isset($data[$delta]['plugin_id']['#translate'])) {
        $data[$delta]['plugin_id']['#translate'] = FALSE;
      }

      $settings = $field[$delta]->settings;
      if (!empty($settings['label']) && is_string($settings['label'])) {
        $data['#label'] = $field->getFieldDefinition()->getLabel();
        $data[$delta]['settings']['#label'] = $this->t('Block settings');
        $data[$delta]['settings']['label'] = [
          '#label' => $this->t('Title'),
          '#text' => $settings['label'],
          '#translate' => TRUE,
        ];
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    parent::setTranslations($field_data, $field);

    foreach (Element::children($field_data) as $delta) {
      $label = $field_data[$delta]['settings']['label'] ?? NULL;
      if (!$label || empty($label['#translate']) || !isset($label['#translation']['#text'])) {
        continue;
      }

      if (!$field->offsetExists($delta)) {
        $translation = $field->getEntity();
        $source_field = $translation->getUntranslated()->get($field->getName());
        $field->offsetSet($delta, $source_field->offsetGet($delta));
      }

      $settings = $field->offsetGet($delta)->settings ?? [];
      $settings['label'] = $label['#translation']['#text'];
      $field->offsetGet($delta)->set('settings', $settings);
    }
  }

}
