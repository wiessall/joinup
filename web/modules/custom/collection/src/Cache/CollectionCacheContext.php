<?php

declare(strict_types=1);

namespace Drupal\collection\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the collection cache context service.
 *
 * Cache context ID: 'collection'.
 */
readonly class CollectionCacheContext implements CacheContextInterface {

  public function __construct(
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): TranslatableMarkup|string {
    return t('Collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    /** @var \Drupal\collection\Entity\CollectionInterface $collection */
    if (($collection = $this->requestStack->getCurrentRequest()->get('node')) && $collection->bundle() === 'collection') {
      return 'collection.' . $collection->id();
    }
    return 'collection.none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $metadata = new CacheableMetadata();
    $metadata->setCacheMaxAge(0);
    return $metadata;
  }

}
