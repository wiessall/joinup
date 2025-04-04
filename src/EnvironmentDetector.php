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

  /**
   * Checks if the code is running inside a GitLab CI/CD pipeline.
   *
   * This function verifies whether the environment is a GitLab pipeline
   * and, optionally, if it belongs to the DIGIT QA project.
   *
   * @return bool
   *   TRUE if running inside a GitLab pipeline, FALSE otherwise.
   */
  public static function isGitlabPipeline(): bool {
    return getenv('GITLAB_CI') === 'true' && getenv('TOOLKIT_PROJECT_ID') === 'digit-joinup';
  }

  /**
   * Determines if the current environment is Acceptance (ACC).
   *
   * The environment is considered Acceptance if the
   * 'joinup_acceptance' module is installed and enabled.
   *
   * @return bool
   *   TRUE if in the Acceptance environment, FALSE otherwise.
   */
  public static function isAcceptance(): bool {
    return \Drupal::moduleHandler()->moduleExists('joinup_acceptance');
  }

  /**
   * Determines if the current environment is Production (PROD).
   *
   * The environment is considered Production if no development packages
   * are installed and the 'joinup_acceptance' module is not enabled.
   *
   * @return bool
   *   TRUE if in the Production environment, FALSE otherwise.
   */
  public static function isProduction(): bool {
    $devPackageExist = !file_exists(DRUPAL_ROOT . '/modules/contrib/devel/devel.info.yml');

    return $devPackageExist && !self::isAcceptance();
  }

  /**
   * Determines if the current environment is part of Behat tests.
   *
   * @return bool
   *   TRUE if in the Production environment, FALSE otherwise.
   */
  public static function isBehatRunning(): bool {
    return defined('BEHAT_TEST_ARE_RUNNING');
  }

}
