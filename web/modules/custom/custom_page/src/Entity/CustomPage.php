<?php

declare(strict_types=1);

namespace Drupal\custom_page\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\NodeCollectionContentTrait;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_core\Entity\PermalinkTrait;
use Drupal\joinup_group\GroupArchivableEntityTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;
use Drupal\topic\Entity\TopicReferencingEntityTrait;

/**
 * Entity class for custom page node entities.
 */
class CustomPage extends Node implements CustomPageInterface {

  use AbstractTrait;
  use ArchivableEntityTrait;
  use GroupArchivableEntityTrait;
  use DescriptionTrait;
  use JoinupBundleClassFieldAccessTrait;
  use EntityPublicationTimeTrait;
  use EntityWorkflowStateTrait;
  use LogoTrait;
  use NodeCollectionContentTrait;
  use TopicReferencingEntityTrait;
  use PermalinkTrait;

  /**
   * {@inheritdoc}
   *
   * This function is a group of hooks that affect memberships according to
   * specific requirements/changes.
   *
   * @see \_collection_meta_entity_handle_coc
   * @see \collection_meta_entity_delete
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if (!$update || !$this->isGroupCodeOfConductPage()) {
      return;
    }

    // The following cases have effects:
    // 1. The page was published and is becoming unpublished. Memberships are
    //    restored because CoC is no longer in effect.
    // 2. The page was unpublished and is becoming published. Memberships are
    //    revoked because CoC is now in effect.
    // 3. The page was updated from published to published. Memberships are
    //    revoked because they have to accept the new CoC.
    $original = $this->original;
    if (empty($original)) {
      return;
    }

    $group = $this->getGroup();
    if (($this->isPublished() && !$original->isPublished()) || ($this->isPublished() && $original->isPublished())) {
      $group->revokeFullMemberships();
    }
    elseif (!$this->isPublished() && $original->isPublished()) {
      $group->restoreFullMemberships();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAbstractField(): string {
    return 'abstract';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'field_paragraphs_body';
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    return 'field_custom_page_logo';
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'field_state';
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupLandingPage(): bool {
    if ($this->hasGroup()) {
      $group = $this->getGroup();
      $landing_page = $group->getSetting('landing_page')->entity;
      return $landing_page && ($landing_page->id() === $this->id());
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupCodeOfConductPage(): bool {
    if ($this->hasGroup() && $this->getGroup() instanceof CollectionInterface) {
      $group = $this->getGroup();
      // Add the nullsafe operator to ensure that this does not break for
      // solutions that do not have the code of conduct functionality.
      $cocPage = $group->getSetting('code_of_conduct')?->entity;
      return $cocPage && ($cocPage->id() === $this->id());
    }
    return FALSE;
  }

  /**
   * Returns an array of field names to skip in ::hasTranslationChanges.
   *
   * @return array
   *   An array of field names.
   */
  protected function getFieldsToSkipFromTranslationChangesCheck(): array {
    $fields = parent::getFieldsToSkipFromTranslationChangesCheck();

    // Prevents auto updates to changed date when we update only state.
    $fields[] = $this->getWorkflowStateFieldName();

    return $fields;
  }

}
