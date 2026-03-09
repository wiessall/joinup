<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\JsonLdObjectInspector;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Behat step definitions for testing SEO.
 */
class JoinupSeoContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;
  use UtilityTrait;

  /**
   * Asserts that a list of meta tags are presented in the page.
   *
   * @codingStandardsIgnoreStart
   * | identifier            | value            |
   * | description           | Some description |
   * | og:title              | My site          |
   * @codingStandardsIgnoreEnd
   * The "identifier" and the "meta value" table headers are mandatory. The
   * "identifier" can be either the name or the property value of the meta
   * entry.
   *
   * @param \Behat\Gherkin\Node\TableNode $list
   *   A list of meta tags to check for.
   *
   * @throws \Exception
   *   Thrown if at least one of the tags is not found or has a wrong value.
   */
  #[Given('the following meta tags should available in the html:')]
  public function assertMetaTagListInPage(TableNode $list): void {
    $page = $this->getSession()->getPage();
    $missing = [];
    $errors = [];
    foreach ($list->getColumnsHash() as $row) {
      $identifier = $row['identifier'];
      $xpath = "//head/meta[@name='{$identifier}' or @property='{$identifier}']/@content";
      $tag = $page->find('xpath', $xpath);
      if (empty($tag)) {
        $missing[$identifier] = $identifier;
      }
      else {
        $row['value'] = $this->escapeStringWithVariables($row['value']);
        $actual = $tag->getText();
        $found = preg_match("#^{$row['value']}$#", $actual) === 1;

        if ($found === FALSE) {
          $errors[] = [
            'property' => $identifier,
            'expected' => $row['value'],
            'found' => $actual,
          ];
        }
      }
    }

    // Construct the error messages for each graph found.
    $error_messages = [];
    if (!empty($missing)) {
      $properties = implode(', ', $missing);
      $error_messages[] = "Meta entries not found: {$properties}";
    }
    foreach ($errors as $error) {
      $error_messages[] = "Wrong property '{$error['property']}' value: Expected '{$error['expected']}' but '{$error['found']}' was found.";
    }

    if (!empty($error_messages)) {
      throw new \Exception(implode("\n", $error_messages));
    }
  }

  /**
   * Asserts that the schema.org metatags are attached in page.
   */
  #[Given('the metatag JSON should be attached in the page')]
  #[Given('the metatag JSON should be attached in the page with context :context')]
  public function assertJsonMetatagsInPage(string $context = '', bool $checkSchemaContext = TRUE): void {
    $json = $this->getMetatagsAsJson();
    $this->assertCondition(!empty($json), 'Entity metadata are found in the table.');
    if (!$checkSchemaContext) {
      return;
    }
    // Assert that the context of the metatag is 'schema.org' to ensure that the
    // correct metatag is loaded and exists.
    $this->assertCondition(array_key_exists('@context', $json), '@context key not found in the metatag array.');

    // Check whether the expected schema exists.
    $failureMessage = 'The @context property value is not set to the appropriate url.';
    if (!$context) {
      $this->assertSameValues('https://schema.org', $json['@context'], $failureMessage);
      return;
    }
    if (array_search($context, $json['@context']) !== FALSE) {
      $this->assertCondition(in_array($context, $json['@context']), $failureMessage);
    }
    else {
      $this->assertCondition(array_key_exists($context, $json['@context']), $failureMessage);
    }
  }

  /**
   * Asserts that the schema.org metatags are attached in page.
   */
  #[Given('the metatag JSON should be attached in the page without context')]
  public function assertJsonMetatagsInPageWithoutSchemaContext(): void {
    $this->assertJsonMetatagsInPage(checkSchemaContext: FALSE);
  }

  /**
   * Asserts that the schema.org metatags are attached in page.
   */
  #[Given('the metatag JSON should not be attached in the page')]
  public function assertMetatagsNotInPage(): void {
    $json = $this->getMetatagsAsJson();
    $this->assertCondition(empty($json), 'Entity metadata are not attached in the page.');
  }

  /**
   * Asserts the amount of entity graphs of a certain type.
   *
   * @param int $count
   *   The number of graphs of the given type.
   * @param string $type
   *   The type of graph which is the schema class.
   */
  #[Given(':count metatag graph of type :type should exist in the page')]
  public function assertNumberOfEntityGraphsExist(int $count, string $type): void {
    $found = 0;
    $json = $this->getMetatagsAsJson(FALSE);
    try {
      $jsonLdInspector = new JsonLdObjectInspector($json);
      $found = $jsonLdInspector->count($type);
    }
    catch (\Exception) {
    }
    $this->assertSameValues($count, $found, "{$count} graph of type {$type} was expected. {$found} were found.");
  }

  /**
   * Asserts a list of properties in the graph identified by a property.
   *
   * @param string $property
   *   The property of which to identify the graph by.
   * @param string $value
   *   The value of the property of which to identify the graph by.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table of properties for the given graph.
   * @param string|null $subProperty
   *   A sub property to look into.
   * @param int|null $delta
   *   If the field contains multiple values, i.e. the pivot is enabled, then
   *   use the $delta variable to check the specific delta.
   *
   * @throws \Exception
   *   Thrown if the graph is not found in the page or the requested sub
   *   property does not exist.
   */
  #[Given('the metatag (sub)graph of the item with :property :value should have the following properties:')]
  #[Given('the metatag (sub)graph of the item with :property :value should have the following :sub_property properties:')]
  #[Given('the metatag (sub)graph of the item with :property :value should have the following properties in index :delta:')]
  #[Given('the metatag (sub)graph of the item with :property :value should have the following :sub_property properties in index :delta:')]
  public function assertPropertiesOfMetatagGraph(
    string $property,
    string $value,
    TableNode $table,
    ?string $subProperty = NULL,
    ?int $delta = NULL,
  ): void {
    try {
      $jsonLdInspector = new JsonLdObjectInspector(
        $this->getMetatagsAsJson(FALSE),
        $property,
        $value,
        $subProperty,
      );
    }
    catch (\Throwable) {
      $template = $subProperty
        ? "No graphs were found that have a property '%s' with value '%s' or none of them contain a '%s' property."
        : "No graphs were found that have a property '%s' with value '%s'.";
      throw new \Exception(sprintf($template, $property, $value, $subProperty));
    }

    $missingProperties = [];
    $wrongValues = [];
    foreach ($table->getColumnsHash() as $row) {
      $actualPropertyValue = $jsonLdInspector->getGraphNodeElementValue($row['property'], $subProperty, $delta, $graphId);
      if (!isset($actualPropertyValue)) {
        $missingProperties[$graphId][] = $row['property'];
        continue;
      }

      $regexp = $this->getValueRegExp($row['value']);
      $found = (bool) preg_match($regexp, $actualPropertyValue);
      if (!$found) {
        $wrongValues[$graphId][] = [
          'property' => $row['property'],
          'expected' => $row['value'],
          'found' => $actualPropertyValue,
        ];
      }
    }

    if (empty($wrongValues) && empty($missingProperties)) {
      return;
    }

    // Construct the error messages for each graph found.
    $error_messages = [];
    foreach (array_keys($missingProperties) as $graphId) {
      $error_messages[] = sprintf(
        "Graph ID %s: The graph is missing the following properties: %s",
        $graphId,
        implode(', ', $missingProperties[$graphId]),
      );
    }
    foreach ($wrongValues as $graphId => $valueLogs) {
      foreach ($valueLogs as $data) {
        $error_messages[] = sprintf(
          "Graph ID %s: Wrong property at '%s' value: Expected '%s' but %s was found.",
          $graphId,
          $data['property'],
          $data['expected'],
          $data['found'],
        );
      }
    }
    throw new \Exception(implode(PHP_EOL, $error_messages));
  }

  /**
   * Returns the regexp matching the expected value (which can have tokens).
   *
   * @param string $value
   *   The expected value (which can have tokens).
   *
   * @return string
   *   The regexp matching the expected value.
   */
  protected function getValueRegExp(string $value): string {
    return '#^' . $this->escapeStringWithVariables($value) . '$#';
  }

  /**
   * Verifies that a graph object exists on the page.
   *
   * @param string $property
   *   The property of the graph to look for.
   * @param string $value
   *   The value of the property of the graph to look for.
   * @param string|null $not
   *   NULL if we want to ensure that the graph is present, or the string "not"
   *   if the graph shouldn't be present.
   */
  #[Given('the metatag graph of the item with :property :value should be present')]
  #[Given('the metatag graph of the item with :property :value should :not be present')]
  public function jsonLdGraphObjectExistsByLabel(string $property, string $value, ?string $not = NULL): void {
    $this->assertCondition(in_array($not, [NULL, 'not'], TRUE), 'The $not parameter must be NULL or "not".');
    $count = 0;
    try {
      $jsonLdInspector = new JsonLdObjectInspector(
        $this->getMetatagsAsJson(FALSE),
        $property,
        $value,
      );
      $count = $jsonLdInspector->count();
    }
    catch (\Throwable) {
    }

    if (!isset($not)) {
      $this->assertCondition(
        $count > 0,
        sprintf(
          "No graphs were found that have a property '%s' with value '%s'.",
          $property,
          $value,
        )
      );
      return;
    }

    $this->assertSameValues(
      0,
      $count,
      sprintf(
        "Graph having '%s' property with value '%s' is present, but it shouldn't.",
        $property,
        $value,
      )
    );
  }

  /**
   * Retrieves the entity metatags as a JSON array from the page.
   *
   * @param bool $associative
   *   Whether an associative array, or an object should be returned. Optional,
   *   defaults to TRUE for BC reasons.
   *
   * @return array|\stdClass|null
   *   The SEO metatags as an array or object, or NULL if not found.
   */
  protected function getMetatagsAsJson(bool $associative = TRUE): array|\stdClass|null {
    $page = $this->getSession()->getPage();
    if ($script = $page->find('xpath', '//script[@type="application/ld+json"]')) {
      $json = $script->getText() ?: $script->getHtml();
      return json_decode($json, $associative);
    }

    return NULL;
  }

}
