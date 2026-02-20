<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Model;

/**
 * Types of issues returned by the PublicCode validator API.
 */
enum ValidationIssueType: string {

  case Warning = 'warning';
  case Error = 'error';

}