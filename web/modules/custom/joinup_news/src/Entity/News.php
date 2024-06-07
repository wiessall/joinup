<?php

declare(strict_types=1);

namespace Drupal\joinup_news\Entity;

use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'news' bundle.
 */
class News extends CommunityContentBase implements NewsInterface {

  use AbstractTrait;
  use ArchivableEntityTrait;
  use LogoTrait;

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
    return 'field_news_logo';
  }

}
