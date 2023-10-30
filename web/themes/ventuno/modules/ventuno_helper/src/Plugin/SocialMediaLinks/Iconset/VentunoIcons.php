<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper\Plugin\SocialMediaLinks\Iconset;

use Drupal\Component\Utility\Html;
use Drupal\social_media_links\IconsetBase;

/**
 * Provides the Ventuno icons set.
 *
 * @Iconset(
 *   id = "ventuno",
 *   publisher = "Ventuno",
 *   name = "Ventuno icons",
 * )
 */
class VentunoIcons extends IconsetBase {

  /**
   * {@inheritdoc}
   */
  public function getStyle(): array {
    return [
      'default' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPath($icon_name, $style): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): string {
    return 'library';
  }

  /**
   * {@inheritdoc}
   */
  public function getIconElement($platform, $style): array {
    /** @var \Drupal\social_media_links\PlatformInterface $platform */
    $class = Html::cleanCssIdentifier($platform->getIconName());

    return [
      '#type' => 'inline_template',
      '#template' => '<span class="icon icon--{{ class }}"></span>',
      '#context' => ['class' => $class],
    ];
  }

}
