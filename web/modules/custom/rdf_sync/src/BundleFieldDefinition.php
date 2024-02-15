<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a custom field storage definition for bundle fields.
 *
 * To use hook_entity_bundle_field_info(), a field definition that marks the
 * field as not base one must be used.
 *
 * @see https://www.drupal.org/node/2280639
 */
class BundleFieldDefinition extends BaseFieldDefinition {

  /**
   * {@inheritdoc}
   */
  public function isBaseField(): bool {
    return FALSE;
  }

}
