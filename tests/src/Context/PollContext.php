<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\poll\PollChoiceInterface;

/**
 * Behat step definitions for testing polls.
 */
class PollContext extends RawDrupalContext {

  use UtilityTrait;
  use TestingEntitiesTrait;

  /**
   * Creates a set of polls.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The poll data.
   */
  #[Given('the following polls:')]
  public function createPolls(TableNode $table): void {
    $storage = \Drupal::entityTypeManager()->getStorage('poll');
    foreach ($table->getColumnsHash() as $values) {
      if (isset($values['choice'])) {
        $values['choice'] = $this->convertChoices($values['choice']);
      }

      /** @var \Drupal\poll\PollInterface $poll */
      $poll = $storage->create($values);
      $poll->save();

      $this->entities['poll'][$poll->id()] = $poll;
    }
  }

  /**
   * Converts string into list of choices.
   *
   * @param string $choiceValues
   *   Choices, split choice by comma.
   *
   * @return array
   *   List of poll id choices.
   */
  protected function convertChoices(string $choiceValues): array {
    $pollChoiceIds = [];

    $choices = $this->explodeDelimitedSeparatedStepArgument($choiceValues);
    foreach ($choices as $choice) {
      $pollChoiceIds[] = $this->createPollChoice($choice)->id();
    }

    return $pollChoiceIds;
  }

  /**
   * Creates poll choice entity.
   *
   * @param string $choice
   *   Choice text.
   *
   * @return \Drupal\poll\PollChoiceInterface
   *   Poll choice entity.
   */
  protected function createPollChoice(string $choice): PollChoiceInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('poll_choice');

    /** @var \Drupal\poll\PollChoiceInterface $pollChoice */
    $pollChoice = $storage->create(['choice' => $choice]);
    $pollChoice->save();

    $this->entities['poll_choice'][$pollChoice->id()] = $pollChoice;

    return $pollChoice;
  }

}
