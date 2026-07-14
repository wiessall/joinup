<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

/**
 * Behat step definitions for testing webform.
 */
class WebformContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Creates webform entities.
   *
   * Table format:
   * @code
   *  | id           | title        | status | elements                                           |
   *  | test_webform | Test webform | open   | [{"#type": "textfield", "#title": "Test element"}] |
   *
   * @endcode
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The webform data.
   */
  #[Given('the following webforms:')]
  public function createWebforms(TableNode $table): void {
    $webformStorage = \Drupal::entityTypeManager()->getStorage('webform');
    $ids = [];
    foreach ($table->getHash() as $values) {
      $this->prepareFields($values);
      $webform = $webformStorage->create($values);
      $webform->save();
      $ids[] = $webform->id();
    }
    $webformsToCleanup = \Drupal::state()->get('behat_cleanup_webform', []);
    \Drupal::state()->set('behat_cleanup_webform', array_unique(
      array_merge($webformsToCleanup, $ids)
    ));
  }

  /**
   * Prepares the fields before creating the entity.
   *
   * @param array $values
   *   The values as they are read from the feature file, passed by reference.
   */
  public function prepareFields(array &$values): void {
    $values['settings'] = ($values['settings'] ?? []) + Webform::getDefaultSettings();
    $values['elements'] = $values['elements'] ? Yaml::encode(Json::decode($values['elements'])) : NULL;
    $values += [
      'id' => $this->getRandom()->machineName(8),
      'title' => $this->getRandom()->string(),
      'status' => WebformInterface::STATUS_OPEN,
      'langcode' => 'en',
    ];
  }

  /**
   * Sets the status of an existing webform.
   *
   * @param string $webform_id
   *   The webform machine name.
   * @param string $status
   *   Either 'open' or 'closed'.
   */
  #[Given('the webform :webform_id is :status')]
  public function setWebformStatus(string $webform_id, string $status): void {
    assert(in_array($status, ['open', 'closed'], TRUE), "Invalid webform status '$status'. Allowed values are 'open' or 'closed'.");
    $webform = Webform::load($webform_id);
    assert($webform instanceof WebformInterface, "The webform '$webform_id' does not exist.");

    $originalStatuses = \Drupal::state()->get('behat_webform_original_status', []);
    if (!isset($originalStatuses[$webform_id])) {
      $originalStatuses[$webform_id] = $webform->getStatus();
      \Drupal::state()->set('behat_webform_original_status', $originalStatuses);
    }

    $webform->setStatus($status)->save();
  }

  /**
   * Cleans up webforms created during the scenario.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The scenario scope.
   */
  #[AfterScenario]
  public function afterScenarioCleanupEntities(AfterScenarioScope $scope): void {
    $webformsToCleanup = \Drupal::state()->get('behat_cleanup_webform', []);
    if (!$webformsToCleanup) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('webform');
    foreach ($webformsToCleanup as $id) {
      $webform = $storage->load($id);
      if (!$webform instanceof WebformInterface) {
        continue;
      }
      $webform->delete();
    }
  }

  /**
   * Restores the status of webforms changed via self::setWebformStatus().
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The scenario scope.
   */
  #[AfterScenario]
  public function afterScenarioRestoreWebformStatus(AfterScenarioScope $scope): void {
    $originalStatuses = \Drupal::state()->get('behat_webform_original_status', []);
    if (!$originalStatuses) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('webform');
    foreach ($originalStatuses as $id => $status) {
      $webform = $storage->load($id);
      if ($webform instanceof WebformInterface) {
        $webform->setStatus($status)->save();
      }
    }
    \Drupal::state()->delete('behat_webform_original_status');
  }

}
