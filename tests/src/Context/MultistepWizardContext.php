<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AwaitTrait;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Defines Behat step definitions for testing the multistep wizard.
 */
class MultistepWizardContext extends RawDrupalContext {

  use AwaitTrait;
  use JavascriptTrait;

  /**
   * Step labels per node type.
   */
  public const array STEPS = [
    'solution' => [
      'Solution information',
      'Contact details',
      'Additional information',
      'Interoperability information',
      'Confirmation',
    ],
  ];

  /**
   * Jumps to a specific step in the wizard for the given node type.
   *
   * @When I jump to the :step of :node wizard
   */
  public function jumpToTheStep(string $step, string $node): void {
    $nodeSteps = self::STEPS[$node] ?? NULL;

    if ($nodeSteps === NULL) {
      throw new \InvalidArgumentException(sprintf("The node type '%s' is not supported.", $node));
    }

    $currentStep = $this->getActiveStepName();
    $currentIndex = array_search($currentStep, $nodeSteps, TRUE);
    if ($currentIndex === FALSE) {
      throw new \RuntimeException(sprintf("Current step '%s' was not found in the step list for '%s'.", $currentStep, $node));
    }

    $targetIndex = array_search($step, $nodeSteps, TRUE);
    if ($targetIndex === FALSE) {
      throw new \InvalidArgumentException(sprintf("The requested step '%s' does not exist for node type '%s'.", $step, $node));
    }

    $direction = $targetIndex > $currentIndex ? 'Next' : 'Previous';
    $stepsToMove = abs($targetIndex - $currentIndex);

    while ($stepsToMove-- > 0) {
      $button = $this->getSession()->getPage()->findButton($direction);
      if (!$button) {
        throw new \RuntimeException(sprintf("The '%s' button was not found on the page.", $direction));
      }
      $button->click();

      if ($this->hasJavascriptSupport()) {
        $this->waitForAjax();
      }
    }

    $newStep = $this->getActiveStepName();
    if ($newStep !== $step) {
      throw new \RuntimeException(sprintf("Failed to navigate to the expected step '%s'. Currently on '%s'.", $step, $newStep));
    }
  }

  /**
   * Retrieves the currently active wizard step label.
   *
   * @return string
   *   The name of the active step.
   */
  protected function getActiveStepName(): string {
    $activeElement = $this->getSession()
      ->getPage()
      ->find('css', '.multi-steps-label .active');

    if (!$activeElement) {
      throw new \RuntimeException('Unable to locate the active wizard step.');
    }

    return trim($activeElement->getText());
  }

}
