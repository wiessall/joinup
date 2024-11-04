<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument;

use Drupal\search_api\Plugin\views\argument\SearchApiStandard;

/**
 * Wraps the Search API argument handler.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api_group")
 */
class SearchApiGroupArgument extends SearchApiStandard {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    $exceptions = $this->definition['exceptions'] ?? [];

    // Avoid any filtering on group for selected viewes.
    if (in_array($this->view->id(), $exceptions, TRUE)) {
      return;
    }

    parent::query($group_by);
  }

}
