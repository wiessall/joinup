<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a field is unique for the given entity type within a bundle.
 *
 * The validation is case-insensitive. Works only for RDF entities. This
 * constraint was created explicitly for the group entities "Short ID" field.
 */
class UniqueShortIdInsensitiveValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(protected readonly Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $fieldItemList = $this->context->getObject()->getParent()->getParent();
    assert($fieldItemList instanceof FieldItemListInterface);
    $group = $fieldItemList->getEntity();
    if (!$group instanceof GroupInterface) {
      throw new \RuntimeException('UniqueShortidInsensitiveConstraint can only be applied to collections and solutions because the SQL like condition is not case insensitive.');
    }

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
      $this->context->addViolation($constraint->message, [
        '%value' => $value,
        '@entity_type' => $group->getEntityType()->getSingularLabel(),
        '@field_name' => $fieldItemList->getFieldDefinition()->getLabel(),
      ]);
    }
  }

}
