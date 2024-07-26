<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Symfony\Component\Yaml\Yaml;
use WebDriver\Exception\JavaScriptError;

/**
 * Context to report JS errors.
 *
 * Relies on js_errors_collector.js in joinup_test module.
 * JS errors will throw exceptions; making behat fail.
 * JS deprecations are only printed; to view them, use behat pretty formatter.
 */
class JsErrorsContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Tag to skip JS errors check (on feature or scenario).
   */
  private const string IGNORE_TAG = 'js-errors';

  /**
   * Whether the check applies on the scenario.
   */
  private bool $applies = FALSE;

  /**
   * Constructs a new context.
   *
   * @param string[]|null $ignoredErrors
   *   (Optional) A list of regex matching errors to ignore.
   * @param string[]|null $ignoredDeprecations
   *   (Optional) A list of regex matching deprecations to ignore.
   */
  public function __construct(
    protected array $ignoredErrors = [],
    protected array $ignoredDeprecations = [],
  ) {}

  /**
   * Check if we should enable looking for js errors.
   *
   * @BeforeScenario @javascript
   */
  public function prepare(BeforeScenarioScope $scope): void {
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Ignore scenarios with the ignore tag.
    $tags = array_unique(array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags()));
    $this->applies = !in_array(self::IGNORE_TAG, $tags, TRUE);
  }

  /**
   * Report JS errors, if any.
   *
   * @AfterStep
   *
   * @throws \WebDriver\Exception\JavaScriptError
   */
  public function reportErrors(AfterStepScope $scope): void {
    if (!$this->applies || !$this->getSession()->isStarted()) {
      return;
    }

    // Get JS errors, abort if none found.
    $errors = $this->getSession()->evaluateScript('return Drupal.jsErrors') ?? [];
    if (!$errors) {
      return;
    }

    // Filter out ignored errors.
    if ($this->ignoredErrors) {
      $errors = array_filter($errors, fn(string $error): bool => !$this->matchPatterns($error, $this->ignoredErrors));
    }

    // Print errors.
    $dump = Yaml::dump($errors, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    $path = sprintf("%s:%d", $scope->getFeature()->getFile(), $scope->getStep()->getLine());
    throw new JavaScriptError(sprintf("%s\n\tFound %d javascript error(s)\n%s", $path, count($errors), $dump));
  }

  /**
   * Report JS Drupal deprecations, if any.
   *
   * @AfterStep
   *
   * @throws \WebDriver\Exception\JavaScriptError
   */
  public function reportDeprecations(AfterStepScope $scope): void {
    if (!$this->applies || !$this->getSession()->isStarted()) {
      return;
    }

    // Get JS deprecations, abort if none found.
    $deprecations = $this->getSession()->evaluateScript('return Drupal.jsDeprecations') ?? [];
    if (!$deprecations) {
      return;
    }

    // Filter out ignored deprecations.
    if ($this->ignoredDeprecations) {
      $deprecations = array_filter($deprecations, fn(string $deprecation): bool => !$this->matchPatterns($deprecation, $this->ignoredDeprecations));
    }

    // Print deprecations.
    $dump = Yaml::dump($deprecations, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    $path = sprintf("%s:%d", $scope->getFeature()->getFile(), $scope->getStep()->getLine());
    print sprintf("%s\n\tFound %d javascript deprecation(s)\n%s", $path, count($deprecations), $dump);
  }

  /**
   * Check if the given string matches any of the regex patterns.
   *
   * @param string $subject
   *   The string to check patterns against.
   * @param string[] $patterns
   *   Array of regex patterns.
   *
   * @return bool
   *   True if it matches, false otherwise.
   */
  private function matchPatterns(string $subject, array $patterns): bool {
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $subject)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
