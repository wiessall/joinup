<?php

declare(strict_types=1);

namespace Drupal\joinup_release;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A field item list class that link the releases to their solution's fields.
 */
class SolutionFieldsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if (!($field = $this->getSetting('target_field_name'))) {
      $this->list = [];
    }

    /** @var \Drupal\joinup_release\Entity\ReleaseInterface $release */
    $release = $this->getEntity();
    if ($release->get('is_version_of')->isEmpty()) {
      $this->list = [];
      // Avoid breaking pages of orphaned or test releases or administration
      // pages that create sample entities, like the field UI.
      return;
    }

    // @todo Change this to load the revision of the solution instead, once
    //   solutions are nodes in ISAICP-8572.
    $solution = $release->getSolution();
    if (!$solution->hasField($field)) {
      throw new \RuntimeException(sprintf('The solution field "%s" does not exist.', $field));
    }

    /** @var \Drupal\Core\Field\FieldConfigInterface $fieldDefinition */
    $fieldDefinition = $solution->getFieldDefinition($field);
    $mainPropertyName = $fieldDefinition->getFieldStorageDefinition()->getMainPropertyName();
    $fieldItemList = $solution->get($field);
    $fieldItemList->filterEmptyItems();

    foreach ($fieldItemList as $delta => $item) {
      $this->list[$delta] = $this->createItem($delta, [$mainPropertyName => $item->{$mainPropertyName}]);
    }
  }

}
