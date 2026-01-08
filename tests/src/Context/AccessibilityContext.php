<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeFeature;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Mink\Element\DocumentElement;
use Behat\Step\Then;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Step definitions for accessibility checks.
 *
 * This context uses Axe, the accessibility engine for automated Web UI testing.
 *
 * @see https://www.deque.com/axe/
 * @see https://github.com/dequelabs/axe-core
 *
 * @todo Consider refactoring as standalone library.
 */
class AccessibilityContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Failure impacts.
   *
   * @var int[]
   *
   * @see https://github.com/dequelabs/axe-core/blob/develop/doc/issue_impact.md
   */
  private const IMPACT = [
    'critical' => 0,
    'serious' => 1,
    'moderate' => 2,
    'minor' => 3,
  ];

  /**
   * Used to build the report.
   *
   * @var int
   */
  private const LINE_LENGTH = 80;

  /**
   * Static cache of the a11y files path.
   */
  private static string $a11yPath;

  /**
   * Current feature file.
   */
  private static string $file;

  /**
   * Current scenario line.
   */
  private int $line;

  public function __construct(
    protected readonly array $standardTags,
    protected readonly array $excludeElements,
    protected readonly array $excludeRules = [],
  ) {}

  /**
   * Asserts that the current page meets accessibility standards.
   *
   * @param string $minimumImpact
   *   The minimum impact to check. Allowed values are keys from self::IMPACT.
   * @param string|null $limitToTags
   *   A comma separated list of accessibility tags to limit on.
   */
  #[Then('the page meets accessibility standards')]
  #[Then('the page meets at least :minimumImpact accessibility standards')]
  #[Then('the page meets :limitToTags accessibility standards')]
  #[Then('the page meets at least :minimumImpact :limitToTags accessibility standards')]
  public function assertAccessible(string $minimumImpact = 'minor', ?string $limitToTags = NULL): void {
    \assert($this->hasJavascriptSupport());
    \assert(isset(self::IMPACT[$minimumImpact]));
    $limitToTags = $limitToTags ? array_map('trim', explode(',', $limitToTags)) : array_keys($this->standardTags);
    $this->runAxeChecks($minimumImpact, $limitToTags);
  }

  /**
   * Runs the Axe accessibility check.
   *
   * @param string $minimumImpact
   *   The minimum impact to check. Allowed values are keys from self::IMPACT.
   * @param string[] $limitToTags
   *   A comma separated list of accessibility tags to limit on.
   */
  protected function runAxeChecks(string $minimumImpact, array $limitToTags): void {
    $axeJsSrc = $this->ensureAxeJsScript();

    // Trigger the Axe check (is asynchronous).
    $this->getSession()->executeScript($this->getJsTriggeringScript($axeJsSrc, $limitToTags));

    // Axe runs asynchronously, we have to wait until the results are gathered.
    $result = $this->getSession()->getPage()->waitFor(
      30,
      function (DocumentElement $page): ?array {
        if ($axeJsElement = $page->find('xpath', '//script[@data-drupal-selector="axe-js"]')) {
          $output = $axeJsElement->getAttribute('data-output');
          return $output ? Json::decode($output) : NULL;
        }
        return NULL;
      }
    );

    if ($result === NULL) {
      throw new \RuntimeException('Cannot run Axe accessibility check.');
    }

    ['violations' => $violations, 'exception' => $exception] = $result;

    if ($exception) {
      throw new \RuntimeException('Exception thrown while running Axe: ' . Json::encode($exception));
    }

    // Filter out failures with low impact.
    $violations = array_filter(
      $violations,
      fn(array $violation): bool => self::IMPACT[$violation['impact']] <= self::IMPACT[$minimumImpact],
    );

    if (!$violations) {
      // Congratulations!
      return;
    }

    // Sort by impact: the most severe on top.
    uasort($violations, function (array $a, array $b): int {
      $a['__impact'] = self::IMPACT[$a['impact']];
      $b['__impact'] = self::IMPACT[$b['impact']];
      return SortArray::sortByKeyInt($a, $b, '__impact');
    });

    $report = [];
    foreach ($violations as $violation) {
      $report[] = $this->buildReportEntry($violation);
    }

    $this->updateFullReport($violations);

    throw new ExpectationFailedException(
      "Detected accessibility standards violations\n" .
      str_repeat("=", self::LINE_LENGTH) . "\n" .
      implode("\n", $report) . "\n" .
      'A full report, containing also the offending HTML elements, has been created at ' . static::getReportPath()
    );
  }

  /**
   * Creates the Axe JavaScript script file and returns its path.
   *
   * @return string
   *   The path to axe.min.js.
   */
  protected function ensureAxeJsScript(): string {
    $file = static::getA11yPath() . '/axe.min.js';
    if (!file_exists($file)) {
      \Drupal::service('file_system')->copy(DRUPAL_ROOT . '/../node_modules/axe-core/axe.min.js', 'public://a11y/');
    }
    Assert::assertFileExists('public://a11y/axe.min.js');
    return \Drupal::service('file_url_generator')->generateString('public://a11y/axe.min.js');
  }

  /**
   * Returns the JavaScript code that triggers the Axe accessibility checks.
   *
   * @param string $axeJsSrc
   *   The path to axe.min.js.
   * @param array $limitToTags
   *   A list of accessibility tags to limit on.
   *
   * @return string
   *   The JS script.
   */
  protected function getJsTriggeringScript(string $axeJsSrc, array $limitToTags): string {
    $options = [
      'runOnly' => [
        'type' => 'tag',
        'values' => $limitToTags,
      ],
    ];

    // Disable excluded rules if configured.
    if (!empty($this->excludeRules)) {
      $options['rules'] = [];
      foreach ($this->excludeRules as $rule) {
        $options['rules'][$rule] = ['enabled' => FALSE];
      }
    }

    $options = Json::encode($options);

    $context = $this->excludeElements
      ? Json::encode(['exclude' => implode(',', $this->excludeElements)])
      : 'body';

    return <<<JavaScript
      (axeJsSrc => {
        const axeRun = () => {
          let output = { violations: [], exception: null };
          const axeJsElement = document.querySelector('script[data-drupal-selector="axe-js"]');
          axeJsElement.dataset.output = '';

          axe.run($context, $options)
            .then(results => {
              output.violations = results.violations;
              axeJsElement.dataset.output = JSON.stringify(output);
            })
            .catch(exception => {
              output.exception = exception;
              axeJsElement.dataset.output = JSON.stringify(output);
            });
        };

        if (document.querySelector('script[data-drupal-selector="axe-js"]')) {
          axeRun();
        } else {
          const axeJsElement = document.createElement('script');
          axeJsElement.src = axeJsSrc;
          axeJsElement.type = 'text/javascript';
          axeJsElement.dataset.drupalSelector = 'axe-js';
          axeJsElement.onload = () => axeRun();
          document.head.append(axeJsElement);
        }
      })('$axeJsSrc');
      JavaScript;
  }

  /**
   * Builds a report entry.
   *
   * @param array $violation
   *   The violation array.
   *
   * @return string
   *   A report entry.
   */
  protected function buildReportEntry(array $violation): string {
    $entry = [
      'ID' => $violation['id'],
      'Impact' => $violation['impact'],
      'Description' => $violation['description'],
      'Help' => $violation['help'],
      'See' => $violation['helpUrl'],
      'Tags' => implode(', ', $violation['tags']),
    ];

    $indent = array_reduce(
      array_keys($entry),
      fn(int $maxKeyLength, string $key): int => max(strlen($key), $maxKeyLength),
      0,
    ) + 2;

    $text = [];
    foreach ($entry as $key => $value) {
      $wrapped = wordwrap(trim($value), self::LINE_LENGTH - $indent);
      $text[] = str_pad("$key:", $indent) . str_replace("\n", "\n" . str_repeat(' ', $indent), $wrapped);
    }

    return implode("\n", $text) . "\n" . str_repeat('-', self::LINE_LENGTH) . "\n";
  }

  /**
   * Updates the report with the given list of violations.
   *
   * @param array $violations
   *   The list of a11y violations.
   */
  protected function updateFullReport(array $violations): void {
    $currentUrl = trim(parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH) ?? '', '/');
    // Remove the base path from the beginning of $path.
    $currentUrl = substr($currentUrl, strlen(trim(base_path(), '/')));

    $report = Yaml::decode(file_get_contents(static::getReportPath()));
    $report[static::$file . ':' . $this->line] = [
      'page' => $currentUrl,
      'violations' => $violations,
    ];
    // Store as YAML (easier to read).
    file_put_contents(static::getReportPath(), Yaml::encode($report));
  }

  /**
   * Returns the path of the full report.
   *
   * @return string
   *   The path.
   */
  protected static function getA11yPath(): string {
    if (!isset(static::$a11yPath)) {
      static::$a11yPath = DRUPAL_ROOT . '/' . PublicStream::basePath() . '/a11y';
      if (!is_dir(static::$a11yPath)) {
        if (!mkdir(static::$a11yPath) || !is_writable(static::$a11yPath)) {
          throw new \RuntimeException('Directory ' . static::$a11yPath . " cannot be created or it's not writable");
        }
      }
    }
    return static::$a11yPath;
  }

  /**
   * Returns the report path.
   *
   * @return string
   *   The path.
   */
  private static function getReportPath(): string {
    return static::getA11yPath() . '/a11y.report.yml';
  }

  /**
   * Initializes a report file.
   *
   * If ::assertAccessible() is called several times during a suite run, we want
   * to consolidate the reports in a single file.
   */
  #[BeforeSuite]
  public static function initializeReport(): void {
    file_put_contents(static::getReportPath(), Yaml::encode([]));
  }

  /**
   * Captures the current feature file.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   The scope object.
   */
  #[BeforeFeature]
  public static function setFeatureFile(BeforeFeatureScope $scope): void {
    static::$file = substr($scope->getFeature()->getFile(), strlen($scope->getEnvironment()->getSuite()->getSetting('paths')[0]) + 1);
  }

  /**
   * Captures the current scenario line.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The scope object.
   */
  #[BeforeScenario('@api&&@javascript')]
  public function setScenarioLine(BeforeScenarioScope $scope): void {
    $this->line = $scope->getScenario()->getLine();
  }

}
