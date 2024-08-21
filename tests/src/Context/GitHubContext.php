<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing GitHub calls.
 */
class GitHubContext extends RawDrupalContext {

  /**
   * Enables the GitHub HTTP request mocking.
   *
   * @BeforeScenario @github_mock&&@api
   *
   * @see \Drupal\joinup_test\Plugin\ServiceMock\GitHubCom
   */
  public function enableGitHubRequestMock(): void {
    \Drupal::state()->set('joinup_test.github_mock.enabled', TRUE);
  }

  /**
   * Disables the GitHub HTTP request mocking.
   *
   * @AfterScenario @github_mock&&@api
   *
   * @see \Drupal\joinup_test\Plugin\ServiceMock\GitHubCom
   */
  public function disableGitHubRequestMock(): void {
    \Drupal::state()->delete('joinup_test.github_mock.enabled');
  }

}
