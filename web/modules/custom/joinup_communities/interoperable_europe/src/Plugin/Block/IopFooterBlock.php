<?php

declare(strict_types = 1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;

/**
 * Provides an IOP footer block.
 *
 * @Block(
 *   id = "interoperable_europe_iop_footer",
 *   admin_label = @Translation("IOP Footer"),
 *   category = @Translation("Custom")
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
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
