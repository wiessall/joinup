<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Computed field which moves sharing info to the content level for SAPI.
 */
class SharedInFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Static cache for the corresponding "shares" meta entity.
   *
   * @var \Drupal\meta_entity\Entity\MetaEntityInterface|null
   */
  protected ?MetaEntityInterface $metaEntity = NULL;

  /**
   * Returns the "shares" meta entity.
   *
   * @return \Drupal\meta_entity\Entity\MetaEntityInterface
   *   The "shares" meta entity.
   */
  protected function getSharedInMetaEntity(): MetaEntityInterface {
    if (!isset($this->metaEntity)) {
      $sharedInMeta = MetaEntity::loadOrCreate('shared_in', $this->getEntity());
      if (!$sharedInMeta->hasField('field_shared_in')) {
        throw new \InvalidArgumentException("Field field_shared_in doesn't exist");
      }
      $this->metaEntity = $sharedInMeta;
    }

    return $this->metaEntity;
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    foreach ($this->getSharedInMetaEntity()->get('field_shared_in')->getIterator() as $delta => $item) {
      assert($item instanceof FieldItemInterface);
      $this->list[$delta] = $this->createItem($delta, $item->getValue());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update): bool {
    $this->metaEntity = NULL;
    $this->valueComputed = FALSE;
    return FALSE;
  }

}
