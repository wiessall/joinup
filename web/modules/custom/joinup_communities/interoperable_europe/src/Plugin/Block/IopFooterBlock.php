<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\BlockBase;
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
   * {@inheritdoc}
   */
  public function build(): array {
    $icons = [
      'linkedin' => [
        'title' => $this->t('LinkedIn'),
        'url' => 'https://www.linkedin.com/groups/12907049/',
      ],
      'twitter' => [
        'title' => $this->t('Twitter'),
        'url' => 'https://twitter.com/InteroperableEU',
      ],
      'youtube' => [
        'title' => $this->t('Youtube'),
        'url' => 'https://www.youtube.com/c/interoperableeurope',
      ],
    ];

    foreach ($icons as $class => $data) {
      $build[$class] = [
        '#type' => 'inline_template',
        '#template' => '<a class="btn btn-sm rounded-circle join__btn join__btn--' . $class . '" href="{{ url }}" target="_blank" rel="noopener" title="{{ title }}"></a>',
        '#context' => [
          'icon' => $class,
          'title' => $this->t('Follow us on @media', ['@media' => $data['title']]),
          'url' => Url::fromUri($data['url']),
        ],
      ];
    }

    $build['newsletter'] = [
      '#type' => 'inline_template',
      '#template' => '<a class="btn btn-sm rounded-circle join__btn join__btn--newsletter" href="{{ url }}" target="_blank" rel="noopener" title="{{ title }}"></a>',
      '#context' => [
        'title' => $this->t('Subscribe to the interoperable europe newsletter'),
        'url' => Url::fromUri('https://ec.europa.eu/newsroom/informatics/user-subscriptions/1115/create'),
      ],
    ];
    return $build;
  }

}
