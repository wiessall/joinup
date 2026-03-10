<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\Validation;

/**
 * Types of validation issues.
 */
enum ValidationIssueType: string {

  case Warning = 'warning';
  case Error = 'error';

}
