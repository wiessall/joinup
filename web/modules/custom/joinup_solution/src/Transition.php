<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

/**
 * Transition to apply on sub content of a solution if it was (un)archived.
 */
enum Transition: string {

  case Archive = 'archive';
  case Unarchive = 'unarchive';

}
