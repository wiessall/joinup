<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing the multistep wizard.
 */
class MultistepWizardContext extends RawDrupalContext {

  /**
   * Steps per node type.
   *
   * @var array
   */
  const array STEPS = [
    'solution' => [
      'Solution information',
      'Contact details',
      'Additional information',
      'Interoperability information',
      'Confirmation',
    ],
  ];

  /**
   * Navigates to the specific wizard step.
   *
   * @When I jump to the :step of :node wizard
   */
  public function jumpToTheStep(string $step, string $node): void {
    $nodeSteps = self::STEPS[$node] ?? [];
    if (empty($nodeSteps)) {
      throw new \RuntimeException("$node node is not supported.");
    }

    $initialActiveName = $this->getActiveStepName();
    $actualIndex = array_search($initialActiveName, $nodeSteps, TRUE);
    if ($actualIndex === FALSE) {
      throw new \RuntimeException('Actual step was not found.');
    }

    $expectedIndex = array_search($step, $nodeSteps, TRUE);
    if ($expectedIndex === FALSE) {
      throw new \RuntimeException('Expected step does not exist in defined steps.');
    }

    $buttonLabel = $actualIndex < $expectedIndex ? "Next" : "Previous";
    $difference = abs($actualIndex - $expectedIndex);

    while ($difference > 0) {
      $this->getSession()
        ->getPage()->findButton($buttonLabel)?->click();
      $difference--;
    }

    $actualActiveName = $this->getActiveStepName();
    if ($actualActiveName !== $step) {
      throw new \RuntimeException("System was not able to jump to $step.");
    }
  }

  /**
   * Gets active step name.
   *
   * @return string
   *   Active step name.
   */
  protected function getActiveStepName(): string {
    $activeStep = $this->getSession()
      ->getPage()
      ->find('css', '.multi-steps-label .active');
    if (!$activeStep) {
      throw new \RuntimeException('Active wizard step was not found.');
    }

    return $activeStep->getText();
  }

}
