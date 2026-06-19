<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Field\FieldType;

use Drupal\collection\Entity\GlossaryTermInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the glossary index letter of a term.
 */
class GlossaryLetterFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();
    assert($entity instanceof GlossaryTermInterface);
    $this->list[0] = $this->createItem(0, $entity->getGlossaryLetter());
  }

}
