<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\node\NodeInterface;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait as ContribNodeCreationTrait;

/**
 * Wraps the `weitzman/drupal-test-traits` library node creation trait.
 */
trait NodeCreationTrait {

  use ContribNodeCreationTrait {
    createNode as contribCreateNode;
  }

  /**
   * {@inheritdoc}
   */
  protected function createNode(array $values = []): NodeInterface {
    $values += [
      // Prevent notifications for test entities creation.
      'skip_notification' => TRUE,
    ];
    return $this->contribCreateNode($values);
  }

}
