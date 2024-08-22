<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\Entity\Node;

/**
 * Bundle class for 'oss_contractor' nodes.
 */
class OssContractor extends Node implements OssContractorInterface {

  use EntityAnonymousOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);
    $fields['uid'] = static::getAnonymousOwnerFieldDefinition($entity_type);
    return $fields;
  }

}
