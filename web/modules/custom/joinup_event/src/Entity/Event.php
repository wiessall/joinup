<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_group\Entity\ShareableContentTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'event' bundle.
 */
class Event extends CommunityContentBase implements EventInterface {

  use JoinupBundleClassFieldAccessTrait;
  use LogoTrait;
  use ArchivableEntityTrait;
  use AbstractTrait;
  use ShareableContentTrait;
  use StringTranslationTrait;

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

  /**
   * {@inheritdoc}
   */
  public function getDateRange(): array {
    $dateRange = $this->get('field_event_date')->getValue();
    return (!empty($dateRange)) ? array_shift($dateRange) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSharingNotification(): ?TranslatableMarkup {
    return $this->t(
      'Please note: You must be subscribed to the target space to share your content.<br>Your event will be shared on that space for six months. After that,<br>it will be considered obsolete and will be automatically removed from that space.'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getShareExpireTimestamp(?int $currentTimestamp): ?int {
    return static::someMonthsLater(6, $currentTimestamp);
  }

}
