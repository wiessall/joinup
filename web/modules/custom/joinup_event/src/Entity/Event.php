<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Entity;

use Drupal\Core\Url;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'event' bundle.
 */
class Event extends CommunityContentBase implements EventInterface {

  use JoinupBundleClassFieldAccessTrait;
  use LogoTrait;
  use ArchivableEntityTrait;
  use AbstractTrait;

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
    return 'field_event_logo';
  }

  /**
   * {@inheritdoc}
   */
  public function getLocation(): ?string {
    return $this->getMainPropertyValue('field_location');
  }

  /**
   * {@inheritdoc}
   */
  public function getOnlineLocation(): ?string {
    return $this->getMainPropertyValue('field_event_online_location');
  }

  /**
   * {@inheritdoc}
   */
  public function getWebUrl(): ?Url {
    /** @var \Drupal\link\LinkItemInterface|null $url */
    $url = $this->getFirstItem('field_event_web_url');

    if (!empty($url)) {
      return $url->getUrl();
    }

    return NULL;
  }

}
