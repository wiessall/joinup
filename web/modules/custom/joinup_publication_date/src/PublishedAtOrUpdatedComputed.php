<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * Shows the publication date, or the update date if the node is unpublished.
 */
class PublishedAtOrUpdatedComputed extends TypedData {

  /**
   * The data value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function getValue(): int {
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->getParent();
    $value = $item->get($this->definition->getSetting('source'))->getValue();
    if ($value) {
      return (int) $value;
    }

    $entity = $item->getEntity();
    if ($entity instanceof EntityChangedInterface) {
      return (int) $entity->getChangedTime();
    }

    // Fall back to the current time if the entity is somehow not a node.
    return \Drupal::time()->getRequestTime();
  }

}
