<?php

declare(strict_types=1);

namespace Drupal\custom_page\Entity;

use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\joinup_group\GroupArchivableEntityInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;
use Drupal\topic\Entity\TopicReferencingEntityInterface;

/**
 * Interface for content page node entities.
 */
interface CustomPageInterface extends NodeInterface, GroupContentInterface, CollectionContentInterface, EntityPublicationTimeInterface, LogoInterface, DescriptionInterface, EntityWorkflowStateInterface, ArchivableEntityInterface, GroupArchivableEntityInterface, TopicReferencingEntityInterface, PermalinkInterface, AbstractInterface, ShareableContentInterface {

  /**
   * Checks if this custom page is set as a landing page of the group.
   *
   * @return bool
   *   Return true if the entity is set as a landing page of the group.
   */
  public function isGroupLandingPage(): bool;

  /**
   * Checks if this custom page is set as a code of conduct page for the group.
   *
   * @return bool
   *   Return true if the entity is set as a code of conduct page.
   */
  public function isGroupCodeOfConductPage(): bool;

  /**
   * Determines whether the title is hidden on this custom page.
   *
   * @return bool
   *   Return true if the title hidden is enabled.
   */
  public function isHiddenTitleEnabled(): bool;

}
