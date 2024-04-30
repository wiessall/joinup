<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date;

use Drupal\Core\TypedData\TypedData;
use Drupal\node\NodeInterface;

/**
 * Shows the publication date, or the creation date if the node is unpublished.
 */
class PublishedAtOrCreatedComputed extends TypedData {

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
    if ($entity instanceof NodeInterface) {
      return (int) $entity->getCreatedTime();
    }

    return \Drupal::time()->getRequestTime();
  }

}
