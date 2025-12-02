<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a field is unique for the given entity type within a bundle.
 *
 * The validation is case-insensitive. This constraint was created specifically
 * for the group entities "Short ID" field.
 */
class UniqueShortIdInsensitiveConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(protected readonly Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $group = $items->getEntity();
    if (!$group instanceof GroupInterface) {
      throw new \RuntimeException('UniqueShortidInsensitiveConstraint can only be applied to collections and solutions because the SQL like condition is not case insensitive.');
    }

    // Short ID has cardinality 1. Does not need to iterate over other deltas.
    $value = $items->first()?->getValue()['value'] ?? NULL;
    if (!$value) {
      return;
    }

    // Validate the format, exit on failure.
    if (!preg_match($constraint->formatRegex, $value)) {
      $this->context->addViolation($constraint->formatInvalidMessage, [
        '%value' => $value,
        '@entity_type' => $group->getEntityType()->getSingularLabel(),
        '@field_name' => $items->getFieldDefinition()->getLabel(),
      ]);

      // @todo Consider using Sequentially once included https://www.drupal.org/project/drupal/issues/3521131
      return;
    }

    // Validate the uniqueness.
    $query = $this->database->select('node__short_id')
      ->fields('node__short_id', ['short_id_value'])
      ->condition('bundle', $group->bundle())
      ->where('LOWER(short_id_value) = :value', [
        ':value' => strtolower($value),
      ]);
    if (!$group->isNew()) {
      $query->condition('entity_id', $group->id(), '<>');
    }

    if ($query->execute()->fetchField()) {
      $this->context->addViolation($constraint->notUniqueMessage, [
        '%value' => $value,
        '@entity_type' => $group->getEntityType()->getSingularLabel(),
        '@field_name' => $items->getFieldDefinition()->getLabel(),
      ]);
    }
  }

}
