<?php

declare(strict_types=1);

namespace Joinup;

/**
 * Class to detect the environment.
 */
class EnvironmentDetector {

  /**
   * Determines if the current environment is local.
   *
   * This function checks the presence of the `IS_DDEV_PROJECT` environment
   * variable. If the variable is set, the environment is assumed to be
   * a DDEV-managed environment (not local). Otherwise, it is considered local.
   *
   * @return bool
   *   TRUE if the environment is local, FALSE otherwise.
   */
  public static function isLocal(): bool {
    return getenv('IS_DDEV_PROJECT') === 'true';
  }

}
