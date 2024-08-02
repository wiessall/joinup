<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;

/**
 * Behat step definitions related to joinup_log entity.
 */
class JoinupLogContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;

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

  /**
   * Creates a set of joinup log.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The joinup log data.
   *
   * @Given the following joinup log:
   */
  public function createJoinupLog(TableNode $table): void {
    $storage = \Drupal::entityTypeManager()->getStorage('joinup_log');
    foreach ($table->getColumnsHash() as $values) {
      foreach (['group', 'user', 'actor', 'type', 'timestamp'] as $required) {
        if (!isset($values[$required])) {
          throw new \Exception("Missing column $required");
        }
      }

      $entityGroup = $this->getEntityByLabel('rdf_entity', $values['group']);
      $values['group'] = $entityGroup->id();

      $user = user_load_by_name($values['user']);
      if (!$user instanceof AccountInterface) {
        throw new \Exception("User {$values['user']} could not be found.");
      }
      $values['uid'] = $user->id();

      $actor = user_load_by_name($values['actor']);
      if (!$actor instanceof AccountInterface) {
        throw new \Exception("Actor {$values['actor']} could not be found.");
      }
      $values['actor'] = $actor->id();

      /** @var \Drupal\joinup_log\Entity\JoinupLogInterface $log */
      $log = $storage->create($values);
      $log->save();

      $this->entities['joinup_log'][$log->id()] = $log;
    }
  }

}
