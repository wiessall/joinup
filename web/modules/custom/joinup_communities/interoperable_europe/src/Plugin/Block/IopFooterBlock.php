<?php

declare(strict_types = 1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Url;
use Drupal\oe_newsroom_newsletter\Form\NewsletterSubscribeForm;

/**
 * Provides an IOP footer block.
 *
 * @Block(
 *   id = "interoperable_europe_iop_footer",
 *   admin_label = @Translation("IOP Footer"),
 *   category = @Translation("Interoperable Europe")
 * )
 */
class IopFooterBlock extends BlockBase {

  /**
   * The plugin context handler.
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $icons = [
      'LinkedIn' => 'internal:#',
      'Twitter' => 'internal:#',
      'Youtube' => 'internal:#',
    ];

    foreach ($icons as $icon => $url) {
      $build[$icon] = [
        '#type' => 'link',
        '#title' => $this->t($icon),
        '#url' => Url::fromUri($url),
        '#attributes' => [
          'class' => ['icon', 'icon-' . strtolower($icon)],
        ],
      ];
    }

    $build['newsletter'] = [
      '#type' => 'link',
      '#url' => Url::fromUri('https://ec.europa.eu/newsroom/informatics/user-subscriptions/1115/create'),
      '#title' => $this->t('Subscribe to our newsletter'),
      '#attributes' => [
        'class' => ['iop-footer__newsletter'],
      ],
    ];
    return $build;
  }

}
