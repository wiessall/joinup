<?php

declare(strict_types=1);

namespace Drupal\joinup_core\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A class providing Drupal Twig dummy text.
 *
 * Prevent errors in twigc by implementing the missing get_dummy_text function,
 * which is referenced in BCL component templates.
 */
class DummyTextTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('get_dummy_text', [$this, 'getDummyText']),
    ];
  }

  /**
   * Gets a dummy text.
   */
  public function getDummyText(): string {
    return 'This is dummy text';
  }

}
