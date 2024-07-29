<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Common code for entities implementing DistributionsParentInterface.
 */
trait DistributionParentTrait {

  /**
   * {@inheritdoc}
   */
  public function getDistributionIds(): array {
    assert($this instanceof DistributionsParentInterface);
    return array_map(
      fn(array $item): int => (int) $item['target_id'],
      $this->get('distributions')->getValue(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDistributions(): array {
    assert($this instanceof DistributionsParentInterface);
    $referenceField = $this->get('distributions');
    assert($referenceField instanceof EntityReferenceFieldItemListInterface);

    $distributions = [];

    foreach ($referenceField->referencedEntities() as $distribution) {
      $distributions[$distribution->id()] = $distribution;
    };
    return $distributions;
  }

}
