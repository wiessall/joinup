<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

/**
 * Behat step definitions for testing webform.
 */
class WebformContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;

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
    foreach ($table->getHash() as $values) {
      $this->prepareFields($values);
      $webform = $webformStorage->create($values);
      $webform->save();
      $this->entities['webform'][$webform->id()] = $webform;
    }
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

}
