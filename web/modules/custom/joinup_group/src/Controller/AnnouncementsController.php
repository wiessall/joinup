<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Event\GroupReportsEvent;
use Drupal\joinup_group\Event\GroupReportsEventInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for the group reports page.
 *
 * This is a page containing various reports about a group and is accessible for
 * facilitators and moderators. Modules can add reports for inclusion in this
 * page by subscribing to the GroupReportsEvent.
 *
 * This page can be reached through the three-dots menu on the group overview.
 */
class AnnouncementsController extends ControllerBase {

  /**
   * The OG access service.
   */
  protected OgAccessInterface $ogAccess;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(OgAccessInterface $og_access) {
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('og.access')
    );
  }

  /**
   * Access check for the group reports page.
   *
   * Only facilitators and moderators have the required 'access group reports'
   * permission.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group for which the access to the reports page is being determined.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $rdf_entity): AccessResultInterface {
    $meta_entity = MetaEntity::loadOrCreate($this->getMetaEntityBundle($rdf_entity), $rdf_entity);
    if ((bool) $meta_entity->announcements_enabled->value) {
      $access_result = $meta_entity->access('update', NULL, TRUE);
      if ($access_result->isAllowed()) {
        return $access_result;
      }
      return $access_result->orIf($this->ogAccess->userAccess($rdf_entity, 'update ' . $this->getMetaEntityBundle($rdf_entity) . ' meta-entity'));
    }
    return AccessResult::forbidden()->addCacheTags($meta_entity->getCacheTags());
  }

  /**
   * Format a meta entity bundle ID.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return string
   *   The meta entity bundle ID.
   */
  private function getMetaEntityBundle(GroupInterface $rdf_entity): string {
    return "{$rdf_entity->bundle()}_settings";
  }

}
