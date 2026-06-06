<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

/**
 * Transition to apply on sub-content of a group when it is (un)archived.
 */
enum Transition: string {

  case Archive = 'archive';
  case Unarchive = 'unarchive';

}
