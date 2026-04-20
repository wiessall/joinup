<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\UserTrait;

/**
 * Behat step definitions related to joinup_log entity.
 */
class JoinupLogContext extends RawDrupalContext {

  use EntityTrait;
  use UserTrait;

  /**
   * Timestamp when the current test scenario was started.
   */
  protected int $timestamp = 0;

  /**
   * Prepare clean-up data.
   */
  #[BeforeScenario]
  public function prepareData(): void {
    $this->timestamp = time();
  }

  /**
   * Navigates to the actions log report for the given group.
   *
   * @param string $group
   *   The name of the group.
   */
  #[When('I go to the actions log report for :group')]
  public function visitActionsLogReport(string $group): void {
    $entityGroup = $this->getEntityByLabel('node', $group);
    $url = Url::fromRoute('view.actions_log.logs', [
      'node' => $entityGroup->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Checks if joinup_log entry exists.
   *
   * @param string $message
   *   Message.
   */
  #[Then('Log entry with the message :message should exist.')]
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

  /**
   * Creates a set of joinup log.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The joinup log data.
   */
  #[Given('the following joinup log:')]
  public function createJoinupLog(TableNode $table): void {
    $storage = \Drupal::entityTypeManager()->getStorage('joinup_log');
    foreach ($table->getColumnsHash() as $values) {
      foreach (['group', 'user', 'type', 'timestamp'] as $required) {
        if (!isset($values[$required])) {
          throw new \Exception("Missing column $required");
        }
      }

      $entityGroup = $this->getEntityByLabel('node', $values['group']);
      $values['group'] = $entityGroup->id();

      $user = $this->getUserByName($values['user']);
      $values['uid'] = $user->id();

      if (!empty($values['actor'])) {
        $actor = $this->getUserByName($values['actor']);
        $values['actor'] = $actor->id();
      }
      else {
        $values['actor'] = NULL;
      }

      /** @var \Drupal\joinup_log\Entity\JoinupLogInterface $log */
      $log = $storage->create($values);
      $log->save();
    }
  }

}
