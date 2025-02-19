<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * Field resolvers override policy.
 */
enum FieldOverridingPolicy: string {

  // Override any existing value in the field (default).
  case Always = 'always';

  // Preserve any non-empty value.
  case PreserveNotEmpty = 'preserve_not_empty';

  // Override only if resolved value is not empty.
  case ResolvedNotEmpty = 'resolved_not_empty';

}
