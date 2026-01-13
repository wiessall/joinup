<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Symfony\Component\Yaml\Yaml;
use WebDriver\Exception\JavaScriptError;

/**
 * Context to report JS errors.
 *
 * Relies on js_testing_log.js injected from joinup_test.
 * JS errors will throw exceptions; making behat fail.
 * Drupal JS deprecations are logged in PHP (E_USER_DEPRECATED).
 */
class JsErrorsContext extends RawDrupalContext {

  public function __construct(
    protected readonly array $ignoredErrors = [],
    protected readonly array $ignoredWarnings = [],
  ) {}

  /**
   * Prepares state.
   */
  #[BeforeScenario('@javascript')]
  public function prepare(): void {
    if (!$this->getSession()->isStarted()) {
      return;
    }

    // Clear the session storage.
    $this->getSession()->executeScript("['js_testing_log_test.errors', 'js_testing_log_test.warnings'].forEach(key => sessionStorage.removeItem(key))");
  }

  /**
   * Reports JS warnings/errors, if any.
   *
   * @throws \WebDriver\Exception\JavaScriptError
   */
  #[AfterScenario('~@js-errors&&@javascript')]
  public function report(AfterScenarioScope $scope): void {
    if (!$this->getSession()->isStarted()) {
      return;
    }

    // Get warnings/errors and clear them from storage.
    $warnings = $this->getSession()->evaluateScript("JSON.parse(sessionStorage.getItem('js_testing_log_test.warnings') || JSON.stringify([]))");
    $errors = $this->getSession()->evaluateScript("JSON.parse(sessionStorage.getItem('js_testing_log_test.errors') || JSON.stringify([]))");
    $this->getSession()->executeScript("['js_testing_log_test.errors', 'js_testing_log_test.warnings'].forEach(key => sessionStorage.removeItem(key))");

    // Report.
    $this->reportWarnings($warnings, $scope);
    $this->reportErrors($errors, $scope);
  }

  /**
   * Reports JS warnings, if any.
   */
  public function reportWarnings(array $warnings, AfterScenarioScope $scope): void {
    if ($this->ignoredWarnings) {
      $warnings = array_filter($warnings, fn(string $warning): bool => !$this->matchPatterns($warning, $this->ignoredWarnings));
    }

    foreach ($warnings as $warning) {
      if (str_starts_with($warning, '[Deprecation]')) {
        $path = sprintf("%s:%d", $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
        @trigger_error(sprintf("%s\n  JS deprecation: %s", $path, substr($warning, 13)), E_USER_DEPRECATED);
      }
    }
  }

  /**
   * Reports JS errors, if any.
   *
   * @throws \WebDriver\Exception\JavaScriptError
   */
  public function reportErrors(array $errors, AfterScenarioScope $scope): void {
    if ($this->ignoredErrors) {
      $errors = array_filter($errors, fn(string $error): bool => !$this->matchPatterns($error, $this->ignoredErrors));
    }

    if (!$errors) {
      return;
    }

    $dump = Yaml::dump($errors, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    $path = sprintf("%s:%d", $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
    throw new JavaScriptError(sprintf("%s\n  Found %d javascript error(s)\n%s", $path, count($errors), $dump));
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
