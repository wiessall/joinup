<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\Core\Entity\EntityInterface;

/**
 * Reusable methods for paragraphs.
 */
trait ParagraphTrait {

  /**
   *
   */
  public function createParagraph(array $values = []): EntityInterface {
    $values += [
      'type' => 'simple_paragraph',
    ];
    $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->create($values);
    $paragraph->save();

    if (method_exists($this, 'markEntityForCleanup')) {
      $this->markEntityForCleanup($paragraph);
    }
    else {
      $this->cleanupEntities[] = $paragraph;
    }
    return $paragraph;
  }

}
