<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\views;

use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;

/**
 * Replacement class restoring removed features between VBO 4.2.7 and 4.3.2.
 *
 * @see \Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm
 * @see https://git.drupalcode.org/project/views_bulk_operations/-/commit/01cee7651049e16b13ec3b12139a36e8066674b
 */
class JoinupVboBulkForm extends ViewsBulkOperationsBulkForm {

  /**
   * {@inheritdoc}
   */
  protected function getBulkOptions(): array {
    if (!isset($this->bulkOptions)) {
      parent::getBulkOptions();

      foreach ($this->options['selected_actions'] as $key => $selected_action_data) {
        $definition = $this->actions[$selected_action_data['action_id']];
        // Check access permission, if defined.
        if (
          !empty($definition['requirements']['_permission']) &&
          !$this->currentUser->hasPermission($definition['requirements']['_permission'])
        ) {
          unset($this->bulkOptions[$key]);
          continue;
        }

        // Check custom access, if defined.
        if (
          !empty($definition['requirements']['_custom_access']) &&
          !$definition['class']::customAccess($this->currentUser, $this->view)
        ) {
          unset($this->bulkOptions[$key]);
        }
      }
    }

    return $this->bulkOptions;
  }

}
