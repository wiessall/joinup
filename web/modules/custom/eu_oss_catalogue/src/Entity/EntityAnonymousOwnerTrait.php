<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Provides a trait for entities that have an owner, defaults to anonymous.
 */
trait EntityAnonymousOwnerTrait {

  use EntityOwnerTrait;

  /**
   * Returns the owner field definition, with anonymous as default value.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The owner field definition.
   */
  public static function getAnonymousOwnerFieldDefinition(EntityTypeInterface $entity_type): FieldDefinitionInterface {
    $definitions = self::ownerBaseFieldDefinitions($entity_type);
    $definition = reset($definitions);
    $definition->setDefaultValueCallback(NULL)->setDefaultValue(0);
    return $definition;
  }

}
