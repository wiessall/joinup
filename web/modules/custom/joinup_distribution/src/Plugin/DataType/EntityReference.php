<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\EntityReference as EntityReferenceOriginal;

/**
 * Defines an 'entity_reference' data type.
 *
 * @todo Drop this class in ISAICP-8572.
 */
class EntityReference extends EntityReferenceOriginal {

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    if (!isset($this->target) && isset($this->id)) {
      if ($this->getTargetDefinition()->getEntityTypeId() === 'rdf_entity' && is_numeric($this->id)) {
        $entity = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load($this->id);
        $this->target = isset($entity) ? $entity->getTypedData() : NULL;
        return $this->target;
      }
    }

    return parent::getTarget();
  }

}
