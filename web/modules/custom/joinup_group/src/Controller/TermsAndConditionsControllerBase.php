<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Terms and conditions controller.
 */
abstract class TermsAndConditionsControllerBase extends ControllerBase {

  public function __construct(
    protected readonly BlockManagerInterface $blockManager,
  ) {}

  /**
   * The simple block ID to load.
   *
   * @return string
   *   The simple block ID including the "simple_block:" prefix.
   */
  abstract protected function getTcaBlockId(): string;

  /**
   * Displays Terms and conditions content.
   *
   * @return array
   *   Render array.
   */
  public function display(): array {
    return $this->blockManager->createInstance($this->getTcaBlockId())->build();
  }

}
