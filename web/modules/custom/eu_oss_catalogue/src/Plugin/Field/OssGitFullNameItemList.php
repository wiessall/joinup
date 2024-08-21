<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list class for the 'oss_git_full_name' field.
 */
class OssGitFullNameItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $this->list = [$this->createItem(0, 'Dummy git full name')];
  }

}
