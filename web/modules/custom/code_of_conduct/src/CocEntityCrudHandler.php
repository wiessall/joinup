<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handler service for Code of Conduct.
 */
final class CocEntityCrudHandler implements CocEntityCrudHandlerInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    #[Autowire(service: 'code_of_conduct.membership_handler')]
    private readonly CocMembershipHandlerInterface $membershipHandler,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function onGroupUpdate(GroupInterface $group): void {
    // Calculate if we need to revoke or grant full memberships. When the
    // collection is updated and is changed from open to closed, AND there is a
    // Code of Conduct page, we need to revoke all full memberships. If the
    // collection is updated and is changed from closed to open, we need to
    // restore all full memberships. 2 special cases here: If the current saved
    // version is not a published one, we don't need to do anything. If the
    // collection is updated and the current version is published, we need to
    // compare with the last published version to decide if we need to revoke
    // or grant full memberships. If there is no previously published version,
    // we only act if the current version is a closed group.
    /** @var \Drupal\collection\Entity\CollectionInterface $group */
    if (!$group->isPublished() || !$group->hasPublishedCodeOfConduct()) {
      return;
    }
    $originalWasClosed = $this->wasLastPublishedRevisionClosed($group);
    if ($originalWasClosed === NULL && $group->isClosed() || ($originalWasClosed === FALSE && $group->isClosed())) {
      // Either there is no previously published version and the current
      // version is a closed group, or the previously published version was an
      // open group and the current version is a closed group.
      $this->membershipHandler->revokeFullMemberships($group);
      $this->messenger->addWarning($this->t('All full memberships have been revoked. Members need to accept the Code of Conduct to become a full member again.'));
    }
    elseif ($originalWasClosed === NULL && !$group->isClosed() || ($originalWasClosed && !$group->isClosed())) {
      // Either there is no previously published version and the current
      // version is an open group, or the previously published version was a
      // closed group and the current version is an open group.
      $this->membershipHandler->restoreFullMemberships($group);
      $this->messenger->addWarning($this->t('All full memberships have been restored.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onCodeOfConductUpdate(CustomPageInterface $customPage): void {
    if (!$customPage->isGroupCodeOfConductPage()) {
      return;
    }

    // The following cases have effects:
    // 1. The page was published and is becoming unpublished. Memberships are
    //    restored because CoC is no longer in effect.
    // 2. The page was unpublished and is becoming published. Memberships are
    //    revoked because CoC is now in effect.
    // 3. The page was updated from published to published. Memberships are
    //    revoked because they have to accept the new CoC.
    $original = $customPage->original;
    if (empty($original)) {
      return;
    }

    $group = $customPage->getGroup();
    if (($customPage->isPublished() && !$original->isPublished()) || ($customPage->isPublished() && $original->isPublished())) {
      $this->membershipHandler->revokeFullMemberships($group);
    }
    elseif (!$customPage->isPublished() && $original->isPublished()) {
      $this->membershipHandler->restoreFullMemberships($group);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onGroupSettingsInsertOrUpdate(MetaEntityInterface $metaEntity, bool $insert = FALSE): void {
    if ($metaEntity->bundle() !== 'collection_settings') {
      return;
    }
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $metaEntity->getTargetEntity();

    // Open collections are not restricted by CoC.
    if (!$group->isClosed()) {
      return;
    }

    $codeOfConduct = $metaEntity->get('code_of_conduct');
    if ($insert && !$codeOfConduct->isEmpty()) {
      $this->membershipHandler->revokeFullMemberships($group);
    }
    else {
      $original = $metaEntity?->original;
      if ($original && !$original->get('code_of_conduct')->equals($codeOfConduct)) {
        $codeOfConduct->isEmpty() ? $this->membershipHandler->restoreFullMemberships($group) : $this->membershipHandler->revokeFullMemberships($group);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onGroupSettingsDelete(MetaEntityInterface $metaEntity): void {
    $group = $metaEntity->getTargetEntity();
    if (!$group instanceof GroupInterface) {
      // This can happen when the deletion of the meta entity is the result of
      // the deletion of the parent entity.
      return;
    }
    $this->membershipHandler->restoreFullMemberships($group);
  }

  /**
   * Checks if the last revision was closed.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return bool|null
   *   TRUE if the last revision was closed, FALSE if it was not closed, or
   *   NULL if there is no previously published revision.
   */
  protected function wasLastPublishedRevisionClosed(GroupInterface $group): ?bool {
    $query = $this->database->select('node_field_revision', 'nr');
    $query->join('node_revision__closed_group', 'nrcg', 'nr.vid = nrcg.revision_id');
    $query->fields('nrcg', ['closed_group_value'])
      ->condition('nr.nid', $group->id())
      ->condition('nr.status', 1);
    if ($currentVid = $group->getRevisionId()) {
      $query->condition('nr.vid', $currentVid, '<');
    }
    $query->orderBy('nr.vid', 'DESC')
      ->range(0, 1);
    $result = $query->execute()->fetchField();
    return is_numeric($result) ? (bool) $result : NULL;
  }

}
