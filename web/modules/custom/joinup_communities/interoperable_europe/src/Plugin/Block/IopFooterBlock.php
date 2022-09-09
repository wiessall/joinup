<?php

declare(strict_types = 1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Url;

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
      'linkedin' => [
        'title' => $this->t('LinkedIn'),
        'url' => 'https://www.linkedin.com/in/interoperableeurope/',
      ],
      'twitter' => [
        'title' => $this->t('Twitter'),
        'url' => 'https://twitter.com/InteroperableEU',
      ],
      'youtube' => [
        'title' => $this->t('Youtube'),
        'url' => 'https://www.youtube.com/c/InteroperableEurope',
      ],
    ];

    foreach ($icons as $class => $data) {
      $build[$class] = [
        '#type' => 'link',
        '#title' => $data['title'],
        '#url' => Url::fromUri($data['url']),
        '#attributes' => [
          'class' => ['icon', 'icon-' . $class],
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
