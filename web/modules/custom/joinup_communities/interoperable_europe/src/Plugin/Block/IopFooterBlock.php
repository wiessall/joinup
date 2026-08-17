<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides an IOP footer block.
 */
#[Block(
  id: "interoperable_europe_iop_footer",
  admin_label: new TranslatableMarkup("IOP Footer"),
  category: new TranslatableMarkup("Interoperable Europe"),
)]
class IopFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $social = [
      'linkedin' => [
        'title' => $this->t('LinkedIn', [], ['context' => 'Logo']),
        'url' => 'https://www.linkedin.com/groups/12907049/',
      ],
      'x' => [
        'title' => $this->t('X', [], ['context' => 'Logo']),
        'url' => 'https://x.com/InteroperableEU',
      ],
      'youtube' => [
        'title' => $this->t('Youtube', [], ['context' => 'Logo']),
        'url' => 'https://www.youtube.com/c/interoperableeurope',
      ],
    ];

    $items = [];
    foreach ($social as $class => $data) {
      $items[] = [
        'class' => $class,
        'url' => Url::fromUri($data['url']),
        'title' => $this->t('Follow us on @media', ['@media' => $data['title']]),
      ];
    }

    $items[] = [
      'class' => 'newsletter',
      'url' => Url::fromUri('https://ec.europa.eu/newsroom/informatics/user-subscriptions/1115/create'),
      'title' => $this->t('Subscribe to the interoperable europe newsletter'),
    ];

    return [
      '#theme' => 'iop_footer',
      '#items' => $items,
    ];
  }

}
