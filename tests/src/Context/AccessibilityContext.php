<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\DocumentElement;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\SortArray;
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
   * Constructs a new context.
   *
   * @param array $standardTags
   *   A list of configurable standard tags as an associative array keyed by tag
   *   and having the tag label as value.
   */
  public function __construct(protected array $standardTags) {}

  /**
   * Asserts that the current page meets accessibility standards.
   *
   * @param string $minimumImpact
   *   The minimum impact to check. Allowed values are keys from self::IMPACT.
   * @param string|null $limitToTags
   *   A comma separated list of accessibility tags to limit on.
   *
   * @Then the page meets accessibility standards
   * @Then the page meets at least :minimumImpact accessibility standards
   * @Then the page meets :limitToTags accessibility standards
   * @Then the page meets at least :minimumImpact :limitToTags accessibility standards
   */
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
    ['violations' => $violations, 'exception' => $exception] = $this->getSession()->getPage()->waitFor(
      30,
      function (DocumentElement $page): ?array {
        if ($axeJsElement = $page->find('xpath', '//script[@data-drupal-selector="axe-js"]')) {
          $output = $axeJsElement->getAttribute('data-output');
          return $output ? Json::decode($output) : NULL;
        }
        throw new \RuntimeException("Can't find the Axe script tag in the page");
      }
    );

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

    if (!$path = tempnam(DRUPAL_ROOT . '/../debug/', 'a11y.')) {
      throw new \RuntimeException('Cannot create a file for the JSON full report');
    }
    file_put_contents($path, Json::encode($violations));
    rename($path, "$path.json");

    throw new ExpectationFailedException(
      "Detected accessibility standards violations\n" .
      str_repeat("=", self::LINE_LENGTH) . "\n" .
      implode("\n", $report) . "\n" .
      "A JSON encoded full report, containing also the offending HTML elements, has been created at $path.json"
    );
  }

  /**
   * Creates the Axe JavaScript script file and returns its path.
   *
   * @return string
   *   The path to axe.min.js.
   */
  protected function ensureAxeJsScript(): string {
    $fileSystem = \Drupal::getContainer()->get('file_system');
    $urlGenerator = \Drupal::getContainer()->get('file_url_generator');
    if (!file_exists("public://axe.min.js")) {
      $fileSystem->copy(DRUPAL_ROOT . '/../node_modules/axe-core/axe.min.js', 'public://');
    }
    Assert::assertFileExists('public://axe.min.js');
    return $urlGenerator->generateString('public://axe.min.js');
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
    $options = Json::encode([
      'runOnly' => [
        'type' => 'tag',
        'values' => $limitToTags,
      ],
    ]);

    return <<<JavaScript
      (axeJsSrc => {
        const axeRun = () => {
          let output = { violations: [], exception: null };
          const axeJsElement = document.querySelector('script[data-drupal-selector="axe-js"]');
          // We'll store the results directly on the HTML tag.
          axeJsElement.dataset.output = ''

          axe.run('body', $options)
            .then(results => {
              output.violations = results.violations;
              axeJsElement.dataset.output = JSON.stringify(output);
            })
            .catch(exception => {
              output.exception = exception;
              axeJsElement.dataset.output = JSON.stringify(output);
            });
        }

        if (document.querySelector('script[data-drupal-selector="axe-js"]')) {
          axeRun();
        }
        else {
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

}
