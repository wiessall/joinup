<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Symfony\Component\Yaml\Yaml;
use WebDriver\Exception\JavaScriptError;

/**
 * Context to report JS errors.
 *
 * Relies on js_errors_collector.js in joinup_test module.
 * JS errors will throw exceptions; making behat fail.
 * Drupal JS deprecations are logged in PHP (E_USER_DEPRECATED).
 */
class JsErrorsContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Constructs a new context.
   *
   * @param string[]|null $ignoredErrors
   *   (Optional) A list of regex matching errors to ignore.
   * @param string[]|null $ignoredWarnings
   *   (Optional) A list of regex matching warnings to ignore.
   */
  public function __construct(
    protected array $ignoredErrors = [],
    protected array $ignoredWarnings = [],
  ) {}

  /**
   * Prepares state.
   *
   * @beforeScenario @javascript
   */
  public function prepare(): void {
    if (!$this->hasJavascriptSupport() || !$this->getSession()->isStarted()) {
      return;
    }

    // Clear the session storage.
    $this->getSession()->executeScript("['joinup_test.js_errors', 'joinup_test.js_warnings'].forEach(key => sessionStorage.removeItem(key))");
  }

  /**
   * Reports JS warnings/errors, if any.
   *
   * @AfterScenario ~@js-errors&&@javascript
   *
   * @throws \WebDriver\Exception\JavaScriptError
   */
  public function report(AfterScenarioScope $scope): void {
    if (!$this->hasJavascriptSupport() || !$this->getSession()->isStarted()) {
      return;
    }

    // Get warnings/errors and clear them from storage.
    $warnings = $this->getSession()->evaluateScript("JSON.parse(sessionStorage.getItem('joinup_test.js_warnings') || JSON.stringify([]))");
    $errors = $this->getSession()->evaluateScript("JSON.parse(sessionStorage.getItem('joinup_test.js_errors') || JSON.stringify([]))");
    $this->getSession()->executeScript("['joinup_test.js_errors', 'joinup_test.js_warnings'].forEach(key => sessionStorage.removeItem(key))");

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
      if (str_starts_with($warning['message'], '[Deprecation]')) {
        $path = sprintf("%s:%d", $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
        @trigger_error(sprintf("%s\n  JS deprecation: %s\n  URL: %s", $path, substr($warning['message'], 13), $warning['url']), E_USER_DEPRECATED);
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
