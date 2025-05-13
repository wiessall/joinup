<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Traits;

use Drupal\joinup_test\Plugin\ServiceMock\GitHubCom;

/**
 * Trait to enable / disable GitHub API request mock.
 */
trait GitHubApiMockTrait {

  /**
   * Status of the GitHub service mock before testing.
   *
   * @var bool|null
   */
  protected ?bool $originalMockStatus;

  /**
   * Enables the GitHub HTTP request mocking.
   */
  public function ensureGitHubApiRequestMock(): void {
    static $wasSetUp;
    if (isset($wasSetUp)) {
      return;
    }

    $this->originalMockStatus = \Drupal::state()->get(GitHubCom::MOCK_STATUS_STATES_KEY);
    \Drupal::state()->set(GitHubCom::MOCK_STATUS_STATES_KEY, TRUE);
  }

  /**
   * Restores the original status of GitHub HTTP request mocking.
   */
  public function restoreGitHubApiRequestMock(): void {
    if (isset($this->originalMockStatus)) {
      \Drupal::state()->set(GitHubCom::MOCK_STATUS_STATES_KEY, $this->originalMockStatus);
      return;
    }
    \Drupal::state()->delete(GitHubCom::MOCK_STATUS_STATES_KEY);
  }

}
