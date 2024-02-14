<?php

declare(strict_types=1);

namespace Drupal\eif\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;
use Drupal\inline_entity_form\InlineFormInterface;

/**
 * Inline form handler for 'eif_wrapper' entity type.
 */
class EifWrapperInlineForm extends EntityInlineForm implements InlineFormInterface {

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles): array {
    $fields = parent::getTableFields($bundles);

    $fields['label']['label'] = $this->t('Qualifier');
    $fields['description'] = [
      'type' => 'field',
      'label' => $this->t('Description'),
      'weight' => 10,
      'display_options' => [
        'type' => 'text_trimmed',
        'settings' => ['trim_length' => 250],
      ],
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isTableDragEnabled($element): bool {
    return FALSE;
  }

}
