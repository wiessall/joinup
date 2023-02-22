<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Entity;

use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for content page node entities.
 */
interface CustomPageInterface extends NodeInterface, GroupContentInterface, CollectionContentInterface, EntityPublicationTimeInterface, LogoInterface, DescriptionInterface {

  /**
   * Validate if custom page is set as a landing page of the group.
   *
   * @return bool
   *   Return true if landing page is set as a landing page of the group.
   */
  public function isGroupLandingPage(): bool;

}
