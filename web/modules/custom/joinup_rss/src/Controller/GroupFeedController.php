<?php

declare(strict_types=1);

namespace Drupal\joinup_rss\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide additional access checks for the collection feed view.
 */
class GroupFeedController extends ControllerBase {

  /**
   * Access check for the group feed view route.
   *
   * @param string $node
   *   The encoded ID of an RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(string $node): AccessResultInterface {
    $loaded_entity = $this->entityTypeManager()->getStorage('node')->load($node);
    return AccessResult::allowedIf(
      $loaded_entity instanceof GroupInterface &&
      $loaded_entity->isPublished()
    )->addCacheableDependency($loaded_entity);
  }

}
