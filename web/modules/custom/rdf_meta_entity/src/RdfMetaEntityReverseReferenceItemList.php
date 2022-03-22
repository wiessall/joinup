<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\meta_entity\MetaEntityReverseReferenceItemList;

/**
 * Provides a field item list class for the reverse reference computed fields.
 */
class RdfMetaEntityReverseReferenceItemList extends MetaEntityReverseReferenceItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();
    /** @var \Drupal\meta_entity\MetaEntityRepositoryInterface $repository */
    $repository = \Drupal::service('rdf_meta_entity.repository');
    if ($rdf_meta_entity = $repository->getMetaEntityForEntity($entity, $this->getSetting('rdf_meta_entity_type_id'))) {
      $this->list[0] = $this->createItem(0, $rdf_meta_entity);
    }
  }

}
