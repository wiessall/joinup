<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\joinup_group\AnnouncementsAccessTrait;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Form\AnnouncementForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for the group announcement form.
 */
class AnnouncementsController extends ControllerBase {

  use AnnouncementsAccessTrait;

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
    return (new static($container->get('class_resolver')))
      ->setJoinupGroupManager($container->get('joinup_group.group_manager'));
  }

  /**
   * Access check for the group announcement form.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $node
   *   The group for which the access to the reports page is being determined.
   * @param \Drupal\joinup_group\Entity\AnnouncementInterface|null $announcement
   *   The announcement, when present in the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $node, ?AnnouncementInterface $announcement = NULL): AccessResultInterface {
    $access = $this->accessAnnouncements($node);
    if ($announcement) {
      // If the current user has no available transition, they can't do
      // anything. The announcement is outside their control.
      $access = $access->andIf(AccessResult::allowedIf((bool) $announcement->getAllowedTransitions())
        ->addCacheableDependency($announcement));
    }
    return $access->addCacheTags(['message_list:announcement']);
  }

  /**
   * Access check for the group announcement form in read-only mode.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group for which the access to the reports page is being determined.
   * @param \Drupal\joinup_group\Entity\AnnouncementInterface|null $announcement
   *   The announcement, when present in the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function accessReadOnly(GroupInterface $rdf_entity, ?AnnouncementInterface $announcement = NULL): AccessResultInterface {
    return AccessResult::allowedIf($rdf_entity->isPublished())->addCacheableDependency($rdf_entity);
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

}
