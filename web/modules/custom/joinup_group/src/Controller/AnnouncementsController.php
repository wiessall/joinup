<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Form\AnnouncementForm;
use Drupal\meta_entity\Entity\MetaEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for the group announcement form.
 */
class AnnouncementsController extends ControllerBase {

  /**
   * The class resolver service.
   */
  protected ClassResolverInterface $classResolver;

  /**
   * Constructs a new controller class instance.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('class_resolver'));
  }

  /**
   * Access check for the group announcement form.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group for which the access to the reports page is being determined.
   * @param \Drupal\joinup_group\Entity\AnnouncementInterface|null $announcement
   *   The announcement, when present in the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $rdf_entity, ?AnnouncementInterface $announcement = NULL): AccessResultInterface {
    $access = AccessResult::allowedIf($rdf_entity->isPublished())->addCacheableDependency($rdf_entity);
    $meta_entity = MetaEntity::loadOrCreate($this->getMetaEntityBundle($rdf_entity), $rdf_entity);
    $access = $access->andIf(AccessResult::allowedIf((bool) $meta_entity->announcements_enabled->value)
      ->addCacheTags([
        'meta_entity_list:' . $this->getMetaEntityBundle($rdf_entity),
        'message_list:announcement',
      ]));

    if ($announcement) {
      // If the current user has no available transition, then they can't do
      // anything. The announcement is outside their control.
      $access = $access->andIf(AccessResult::allowedIf((bool) $announcement->getTransitions())
        ->addCacheableDependency($announcement));
    }

    return $access;
  }

  /**
   * Provide a callback to add or edit announcements.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where the announcement belongs.
   * @param \Drupal\joinup_group\Entity\AnnouncementInterface|null $announcement
   *   (optional) The announcement. If omitted, will create a new announcement.
   *
   * @return array
   *   The form render array.
   */
  public function editForm(GroupInterface $rdf_entity, ?AnnouncementInterface $announcement = NULL): array {
    if (!$announcement) {
      $announcement = $this->entityTypeManager()->getStorage('message')->create([
        'template' => 'announcement',
        'group' => $rdf_entity,
        'state' => 'draft',
        'uid' => $this->currentUser()->id(),
      ]);
    }
    elseif ($announcement->getGroupId() != $rdf_entity->id()) {
      throw new \LogicException("Announcement {$announcement->id()} doesn't belong to group {$rdf_entity->id()}");
    }
    $form = $this->classResolver->getInstanceFromDefinition(AnnouncementForm::class)
      ->setAnnouncement($announcement);
    return $this->formBuilder()->getForm($form);
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
