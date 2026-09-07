<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\linkchecker\LinkCheckerLinkStorageSchema;

/**
 * Add an index for the `group` column.
 */
class JoinupCoreLinkCheckerLinkStorageSchema extends LinkCheckerLinkStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $schema['linkchecker_link']['indexes']['group'] = ['group'];
    return $schema;
  }

}
