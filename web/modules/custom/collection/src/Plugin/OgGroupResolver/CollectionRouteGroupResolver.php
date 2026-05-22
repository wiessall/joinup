<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\OgGroupResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;

/**
 * Resolves the collection from the route.
 */
#[OgGroupResolver(
  id: 'collection_from_route',
  label: new TranslatableMarkup('Collection entity from current route'),
  description: new TranslatableMarkup('Checks if the current route is a collection entity path.')
)]
class CollectionRouteGroupResolver extends RouteGroupResolver {

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/node/{node}/glossary/{letter}' => 'node',
      '/node/{node}/solutions' => 'node',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if (($group = $this->getContentEntity()) && $group instanceof CollectionInterface) {
      $collection->addGroup($group, ['route']);
      $this->stopPropagation();
    }
  }

}
