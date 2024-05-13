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

  /**
   * {@inheritdoc}
   */
  public function getPublicationTime(): ?int {
    // This method will return the value of the field_document_publication_date
    // field, if set, otherwise, the value from the published_at property. If
    // none of them are set, it will return NULL.
    $publication_date_item_list = $this->get('field_document_publication_date');
    if (!$publication_date_item_list->isEmpty() && $value = $publication_date_item_list->first()->value) {
      return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value, new \DateTimeZone('UTC'))->getTimestamp();
    }

    if ($this->get('published_at')->isEmpty() || empty($this->get('published_at')->value) || is_null($this->get('published_at')->value)) {
      return NULL;
    }

    return (int) $this->get('published_at')->value;
  }

}
