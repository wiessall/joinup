<?php

declare(strict_types=1);

namespace Drupal\joinup_news\Entity;

use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Interface for news entities in Portal.
 */
interface NewsInterface extends CommunityContentInterface, LogoInterface, ArchivableEntityInterface, AbstractInterface {

}
