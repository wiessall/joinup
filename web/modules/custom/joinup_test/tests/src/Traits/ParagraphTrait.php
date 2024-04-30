<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Reusable methods for paragraphs.
 */
trait ParagraphTrait {

  /**
   * Creates a paragraph and marks it for cleanup.
   *
   * @param array $values
   *   The values to use for the paragraph.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  public function createParagraph(array $values = []): ParagraphInterface {
    $values += [
      'type' => 'simple_paragraph',
    ];
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
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
