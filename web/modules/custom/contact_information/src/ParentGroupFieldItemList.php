<?php

declare(strict_types=1);

namespace Drupal\contact_information;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_group\JoinupGroupHelper;

/**
 * Field item list class for the contact information 'parent_group' field.
 */
class ParentGroupFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    /** @var \Drupal\contact_information\Entity\ContactInformationInterface $contact_info */
    $contact_info = $this->getEntity();
    if ($contact_info->isNew()) {
      return;
    }
    $gids = \Drupal::entityTypeManager()->getStorage('node')
      ->getQuery()->accessCheck(FALSE)
      ->condition('type', array_values(JoinupGroupHelper::GROUP_BUNDLES), 'IN')
      ->condition('contact', $contact_info->id())
      ->execute();
    if ($gids) {
      $this->list[0] = $this->createItem(0, ['target_id' => reset($gids)]);
    }
  }

}
