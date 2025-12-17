<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\argument;

use Drupal\Core\Database\Database;
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
    $argument = $this->argument;
    if (!empty($this->options['transform_dash'])) {
      $argument = strtr($argument, '-', ' ');
    }

    if (!empty($this->options['break_phrase'])) {
      $this->unpackArgumentValue();
    }
    else {
      $this->value = [$argument];
      $this->operator = 'or';
    }

    // Support case-insensitive substring comparisons for PostgreSQL by
    // converting the arguments to lowercase.
    if ($this->options['case'] != 'none' && Database::getConnection()->databaseType() == 'pgsql') {
      foreach ($this->value as $key => $value) {
        $this->value[$key] = mb_strtolower($value);
      }
    }

    if (!empty($this->definition['many to one'])) {
      if (!empty($this->options['glossary'])) {
        $this->helper->formula = TRUE;
      }
      $this->helper->ensureMyTable();
      $this->helper->addFilter();
      return;
    }

    $this->ensureMyTable();
    $formula = FALSE;
    if (empty($this->options['glossary'])) {
      $field = "$this->tableAlias.$this->realField";
    }
    else {
      $formula = TRUE;
      $field = $this->getFormula();
    }

    if (count($this->value) > 1) {
      $operator = 'IN';
      $argument = $this->value;
    }
    else {
      $operator = '=';
    }

    if ($formula) {
      $placeholder = $this->placeholder();
      if ($operator == 'IN') {
        $field .= " IN($placeholder)";
      }
      else {
        $field .= ' = ' . $placeholder;
      }
      $placeholders = [
        $placeholder => $argument,
      ];
      $this->query->addWhereExpression(0, $field, $placeholders);
    }
    else {
      if ($argument === 'EU') {
        $this->query->addWhere(0, $field, NULL, 'IS NULL');
      }
      else {
        $this->query->addWhere(0, $field, $argument, $operator);
      }
    }
  }

}
