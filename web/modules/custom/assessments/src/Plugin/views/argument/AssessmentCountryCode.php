<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Argument handler for the assessment country code.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'assessment_country_code',
)]
class AssessmentCountryCode extends StringArgument {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    // "EU" is always valid and filters reports where country is NULL.
    if ($this->argument === 'EU') {
      $this->ensureMyTable();
      $field = "$this->tableAlias.$this->realField";
      $this->query->addWhere(0, $field, NULL, 'IS NULL');
      return;
    }

    parent::query($group_by);
  }

}
