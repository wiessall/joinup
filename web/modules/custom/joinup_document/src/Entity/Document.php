<?php

declare(strict_types=1);

namespace Drupal\joinup_document\Entity;

use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'document' bundle.
 */
class Document extends CommunityContentBase implements DocumentInterface {

  use LogoTrait;
  use AbstractTrait;
  use ArchivableEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'field_paragraphs_body';
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
  public function getLogoFieldName(): string {
    return 'field_document_logo';
  }

}
