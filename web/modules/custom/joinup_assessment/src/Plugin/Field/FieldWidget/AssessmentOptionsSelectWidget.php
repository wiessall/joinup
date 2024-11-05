<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_assessment\AssessmentInterface;

/**
 * Plugin implementation of the 'assessment_options_select' widget.
 */
#[FieldWidget(
  id: 'assessment_options_select',
  label: new TranslatableMarkup('Assessment select list'),
  field_types: [
    'list_string',
  ],
  multiple_values: TRUE,
)]
class AssessmentOptionsSelectWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity): array {
    $options = parent::getOptions($entity);

    if (!$this->isAssessment($entity) && isset($options[AssessmentInterface::DOCUMENT_TYPE_KEY])) {
      unset($options[AssessmentInterface::DOCUMENT_TYPE_KEY]);
    }

    return $options;
  }

  /**
   * Checks if it is assessment content.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which to return options.
   *
   * @return bool
   *   TRUE if is it.
   */
  protected function isAssessment(FieldableEntityInterface $entity): bool {
    if (!isset($entity->assessment)) {
      return FALSE;
    }

    return !$entity->get('assessment')->isEmpty();
  }

}
