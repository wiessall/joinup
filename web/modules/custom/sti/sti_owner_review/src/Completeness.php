<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\field\FieldConfigInterface;
use Drupal\node\NodeInterface;

/**
 * Which required fields an owner leaves empty.
 *
 * "Required" is Drupal's own setting on the owner's fields (Structure » Content types » Owner » Manage fields), so
 * the list is changed in the UI. The workflow state field is required too, but it is not information to fill.
 */
final class Completeness {

  /**
   * @return string[]
   *   Machine names of the owner's required fields.
   */
  public static function requiredFields(NodeInterface $owner): array {
    $fields = [];
    foreach ($owner->getFieldDefinitions() as $name => $definition) {
      if ($definition instanceof FieldConfigInterface && $definition->isRequired() && $definition->getType() !== 'state' && $name !== 'field_state') {
        $fields[] = $name;
      }
    }
    return $fields;
  }

  /**
   * @return string[]
   *   The required fields that are empty.
   */
  public static function gaps(NodeInterface $owner): array {
    return array_values(array_filter(self::requiredFields($owner), fn($name) => $owner->get($name)->isEmpty()));
  }

}
