<?php

declare(strict_types = 1);

namespace Drupal\joinup_collection\Cache\Context;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;

/**
 * Cache context which varies by the existence of the Joinup collection.
 *
 * Cache context ID: 'joinup_collection_exists'.
 */
class JoinupCollectionExistsCacheContext implements CacheContextInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new JoinupCollectionCacheContext service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Joinup collection exists');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): int {
    $collection = $this->entityTypeManager->getStorage('rdf_entity')->load(JoinupCollectionHelper::getCollectionId());
    return (int) ($collection instanceof CollectionInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $metadata = new CacheableMetadata();
    $metadata->addCacheTags(['rdf_entity_list']);
    return $metadata;
  }

}
