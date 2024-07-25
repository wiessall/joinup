<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions related to joinup_log entity.
 */
class JoinupLogContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Timestamp when the current test scenario was started.
   */
  protected int $timestamp = 0;

  /**
   * Prepare clean-up data.
   *
   * @BeforeScenario
   */
  public function prepareData(): void {
    $this->timestamp = time();
  }

  /**
   * Navigates to the actions log report for the given group.
   *
   * @param string $group
   *   The name of the group.
   *
   * @When I go to the actions log report for :group
   */
  public function visitActionsLogReport(string $group): void {
    $entityGroup = $this->getEntityByLabel('rdf_entity', $group);
    $url = Url::fromRoute('view.actions_log.logs', [
      'rdf_entity' => $entityGroup->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Checks if joinup_log entry exists.
   *
   * @param string $message
   *   Message.
   *
   * @Then Log entry with the message :message should exist.
   */
  public function joinupLogEntryShouldExist(string $message): void {
    $count = \Drupal::entityQuery('joinup_log')
      ->accessCheck(FALSE)
      ->condition('timestamp', $this->timestamp, '>=')
      ->condition('message', $message)
      ->count()
      ->execute();

    if ($count === 0) {
      throw new \RuntimeException("Log with message '$message' was not found.");
    }
  }

}
