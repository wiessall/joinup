<?php

declare(strict_types=1);

namespace Drupal\joinup_validation\Plugin\Validation\Constraint;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\joinup_core\Traits\FieldItemsTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the "Required if other field has value" constraint.
 */
class RequiredIfOtherFieldHasValueConstraintValidator extends ConstraintValidator {

  use FieldItemsTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    // If the field has a value, bail out.
    if (!$items->isEmpty()) {
      return;
    }

    $entity = $items->getEntity();
    /** @var \Drupal\joinup_validation\Plugin\Validation\Constraint\RequiredIfOtherFieldHasValueConstraint $constraint */
    /** @var \Drupal\Core\Field\FieldItemListInterface $dependent_field */
    $dependent_field = $entity->get($constraint->field);

    // If the dependent field is empty too, then the current field is not
    // required.
    if ($dependent_field->isEmpty()) {
      return;
    }

    $dependent_values = $this->getFieldItemListMainPropertyValues($dependent_field);

    $dependent_field_definition = $dependent_field->getFieldDefinition();
    $dependent_item_class = $dependent_field_definition->getItemDefinition()->getClass();
    if (is_a($dependent_item_class, EntityReferenceItem::class, TRUE)) {
      $dependent_field_storage_definition = $dependent_field_definition->getFieldStorageDefinition();
      if ($dependent_field_storage_definition->getSetting('target_type') === 'taxonomy_term') {
        $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
        $terms = array_values($term_storage->loadMultiple($dependent_values));
        $dependent_values = array_map(
          function (TermInterface $term): int|string {
            return $term instanceof RdfSyncEntityInterface ? $term->getUri() : $term->id();
          },
          $terms,
        );
      }
    }

    $matched_values = array_intersect($dependent_values, $constraint->values);
    if (!$matched_values) {
      return;
    }

    $labels = [];
    foreach (array_keys($matched_values) as $delta) {
      $labels[] = $this->getFieldItemDisplayValue($dependent_field->get($delta));
    }

    $message = count($labels) > 1 ? $constraint->multipleValuesMessage : $constraint->message;
    $this->context->addViolation($message, [
      '%field' => $items->getFieldDefinition()->getLabel(),
      '%dependent_field' => $dependent_field->getFieldDefinition()->getLabel(),
      '%dependent_value' => implode(', ', $labels),
    ]);
  }

}
