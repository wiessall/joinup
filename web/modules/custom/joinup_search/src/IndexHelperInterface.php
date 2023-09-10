<?php

declare(strict_types = 1);

namespace Drupal\joinup_search;

/**
 * Interface for the helper class for doing operations related to the index.
 */
interface IndexHelperInterface {

  /**
   * Re-indexes the given items.
   *
   * @param string $entityType
   *   The entity type.
   * @param array $entityIds
   *   The entity IDs.
   * @param string $language
   *   (optional) The language code. Defaults to 'en'.
   * @param array $indexIds
   *   (optional) The index IDs. Defaults to ['published'].
   */
  public function reIndexItems(string $entityType, array $entityIds, string $language = 'en', array $indexIds = ['published']): void;

}
