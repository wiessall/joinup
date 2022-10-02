<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\collection\Entity\NodeCollectionContentTrait;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeTrait;
use Drupal\node\Entity\Node;

/**
 * Entity class for custom page node entities.
 */
class CustomPage extends Node implements CustomPageInterface {

  use DescriptionTrait;
  use EntityPublicationTimeTrait;
  use LogoTrait;
  use NodeCollectionContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    return 'field_custom_page_logo';
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
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->hasField('field_attachment') || $this->get('field_attachment')->isEmpty()) {
      return;
    }

    // We have set the 'default_display' flag of the attachment field to true
    // however, this only has any effect in the form. Ensure that the property
    // is set to true by default for entities created programmatically.
    foreach ($this->get('field_attachment') as $item) {
      if ($item->display === NULL) {
        $item->set('display', 1);
      }
    }
  }

}
