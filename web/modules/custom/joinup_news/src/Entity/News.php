<?php

declare(strict_types=1);

namespace Drupal\joinup_news\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_group\Entity\ShareableContentTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'news' bundle.
 */
class News extends CommunityContentBase implements NewsInterface {

  use AbstractTrait;
  use ArchivableEntityTrait;
  use LogoTrait;
  use ShareableContentTrait;
  use StringTranslationTrait;

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

  /**
   * {@inheritdoc}
   */
  public function getSharingNotification(): ?TranslatableMarkup {
    return $this->t(
      'Please note: You must be subscribed to the target space to share your content.<br>Your news item will be shared on that space for one month. After that,<br>it will be considered obsolete and will be automatically removed from that space.'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getShareExpireTimestamp(?int $currentTimestamp): ?int {
    return static::someMonthsLater(1, $currentTimestamp);
  }

}
