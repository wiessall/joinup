<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Entity;

use Drupal\collection\Entity\NodeCollectionContentTrait;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassMetaEntityTrait;
use Drupal\joinup_core\Entity\OutdatedContentTrait;
use Drupal\joinup_core\Entity\PermalinkTrait;
use Drupal\joinup_featured\FeaturedContentTrait;
use Drupal\joinup_group\Entity\PinnableGroupContentTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_group\GroupArchivableEntityTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;
use Drupal\topic\Entity\TopicReferencingEntityTrait;

/**
 * Base class for community content entities.
 *
 * @todo Once we are on PHP 7.3 we should no longer include
 *   JoinupBundleClassMetaEntityTrait.
 */
abstract class CommunityContentBase extends Node implements CommunityContentInterface {

  use EntityPublicationTimeTrait;
  use EntityWorkflowStateTrait;
  use FeaturedContentTrait;
  use JoinupBundleClassMetaEntityTrait;
  use NodeCollectionContentTrait;
  use OutdatedContentTrait;
  use PinnableGroupContentTrait;
  use TopicReferencingEntityTrait;
  use GroupArchivableEntityTrait;
  use DescriptionTrait;
  use PermalinkTrait;

  /**
   * {@inheritdoc}
   */
  public function getPinnableGroups(): array {
    try {
      $group = $this->getGroup();
    }
    catch (MissingGroupException $e) {
      return [];
    }
    return [$group->id() => $group];
  }

  /**
   * {@inheritdoc}
   */
  public function getPinnableGroupIds(): array {
    try {
      $id = $this->getGroupId();
    }
    catch (MissingGroupException $e) {
      return [];
    }
    return [$id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'body';
  }

}
