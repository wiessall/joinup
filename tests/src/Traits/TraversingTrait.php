<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use WebDriver\Exception\StaleElementReference;

/**
 * Helper methods to deal with traversing of page elements.
 */
trait TraversingTrait {

  use AssertTrait;
  use NameMappingsTrait;

  /**
   * Searches for any kind of field in a form by label.
   *
   * @param string $field
   *   The field label.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (Optional) The region to search in. If a region is not provided, the
   *   whole page will be used.
   *
   * @return \Behat\Mink\Element\TraversableElement|null
   *   The field element or NULL if not found.
   */
  protected function findAnyFormField(string $field, ?TraversableElement $region = NULL): ?TraversableElement {
    if (!$region) {
      $region = $this->getSession()->getPage();
    }

    $element = NULL;
    if (!$element = $region->findField($field)) {
      // Complex fields in Drupal might not be directly linked to actual field
      // elements such as 'select' and 'input', so try both the standard
      // findField() as well as an XPath expression that finds the given label
      // inside any element marked as a form item.
      $xpath = '//*[contains(concat(" ", normalize-space(@class), " "), " form-item ") and .//label[text() = "' . $field . '"]] | //*[contains(concat(" ", normalize-space(@class), " "), " form-wrapper ") and .//h4[text() = "' . $field . '"]] | //*[contains(concat(" ", normalize-space(@class), " "), " col-form-label ") and .//span[text() = "' . $field . '"]]';
      $element = $region->find('xpath', $xpath);
    }

    return $element;
  }

  /**
   * Retrieves a select field by label.
   *
   * @param string $select
   *   The name of the select element.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region in which to search for the select. Defaults to the
   *   whole page.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The select element.
   *
   * @throws \Exception
   *   Thrown when no select field is found.
   */
  protected function findSelect(string $select, ?TraversableElement $region = NULL): TraversableElement {
    if (empty($region)) {
      $region = $this->getSession()->getPage();
    }
    /** @var \Behat\Mink\Element\NodeElement $element */
    $element = $region->find('named', ['select', $select]);

    if (empty($element)) {
      throw new \Exception("Select field '{$select}' not found.");
    }

    return $element;
  }

  /**
   * Helper method that asserts a selected option of a select element.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The select node element.
   * @param string $expected
   *   The select option.
   *
   * @throws \Exception
   *   Thrown if there is no selected option or the selected option is not the
   *   correct one.
   */
  protected function assertSelectedOption(NodeElement $element, string $expected): void {
    $option_element = $element->find('xpath', '//option[@selected="selected"]');
    if (!$option_element) {
      throw new \Exception('No option is selected in the requested select');
    }
    $actual = $option_element->getText();

    // Ignore duplicated whitespace.
    $actual = preg_replace("/\s{2,}/", " ", $actual);
    $expected = preg_replace("/\s{2,}/", " ", $expected);

    if (trim($actual) !== $expected) {
      throw new \Exception(sprintf('The option "%s" was not selected in the page %s, %s was selected', $expected, $this->getSession()
        ->getCurrentUrl(), $option_element->getHtml()));
    }
  }

  /**
   * Helper method that asserts the available options of select fields.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The select element.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The available list of options.
   *
   * @throws \Exception
   *    Throws an exception when the select is not found or options are not
   *    identical.
   */
  protected function assertSelectAvailableOptions(NodeElement $element, TableNode $table): void {
    // Ignore duplicated whitespace.
    $strip_multiple_spaces = function (string $option): string {
      return preg_replace("/\s{2,}/", " ", $option);
    };

    $actual_options = $this->getSelectOptions($element);
    $expected_options = $table->getTable();

    foreach ($expected_options as $expected_option) {
      $actual_option = array_shift($actual_options);

      $this->assertSameValues($strip_multiple_spaces($expected_option[0]), $strip_multiple_spaces($actual_option['text']));

      if (isset($expected_option[1])) {
        $this->assertSameValues($expected_option[1], (string) $actual_option['indentation']);
      }
    }
  }

  /**
   * Retrieves the options of a select field.
   *
   * @param \Behat\Mink\Element\NodeElement $select
   *   The select element.
   *
   * @return array
   *   The options as a structured array keyed by option value, with the
   *   following elements:
   *   - text: The option text.
   *   - indentation: The indentation level.
   */
  protected function getSelectOptions(NodeElement $select): array {
    $options = [];
    foreach ($select->findAll('xpath', '//option') as $element) {
      /** @var \Behat\Mink\Element\NodeElement $element */
      $options[] = [
        'text' => trim($element->getText()),
        'indentation' => substr_count($element->getHtml(), '&nbsp;'),
      ];
    }

    return $options;
  }

  /**
   * Retrieves the optgroups of a select field.
   *
   * @param \Behat\Mink\Element\NodeElement $select
   *   The select element.
   *
   * @return array
   *   The optgroups labels.
   */
  protected function getSelectOptgroups(NodeElement $select): array {
    $optgroups = [];
    foreach ($select->findAll('xpath', '//optgroup') as $element) {
      /** @var \Behat\Mink\Element\NodeElement $element */
      $optgroups[] = trim($element->getAttribute('label'));
    }

    return $optgroups;
  }

  /**
   * Finds a vertical tab by its title.
   *
   * @param string $tab
   *   The title of the vertical tab.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The vertical tab element.
   *
   * @throws \Exception
   *   Thrown when no tab element is found.
   */
  protected function findVerticalTab(string $tab): NodeElement {
    // Xpath to find the vertical tabs.
    $xpath = "//li[@class and contains(concat(' ', normalize-space(@class), ' '), ' vertical-tabs__menu-item ')]";
    // Filter down to the tab containing a link with the provided text.
    $xpath .= "[.//a[./@href]/strong[@class and contains(concat(' ', normalize-space(@class), ' '), ' vertical-tabs__menu-item-title ')]"
      . "[normalize-space(string(.)) = '$tab']]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);

    if ($element === NULL) {
      throw new \Exception('Tab not found: ' . $tab);
    }

    return $element;
  }

  /**
   * Retrieves a region container from the page.
   *
   * @param string $region
   *   The region label as defined in the behat.yml.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The region element.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   */
  protected function getRegion(string $region): NodeElement {
    $session = $this->getSession();
    $regionObj = $session->getPage()->find('region', $region);
    if (!$regionObj) {
      throw new \Exception(sprintf('No region "%s" found on the page %s.', $region, $session->getCurrentUrl()));
    }
    return $regionObj;
  }

  /**
   * Returns the tiles found in the page or a region of it.
   *
   * @param string|null $region
   *   The region label. If no region is provided, the search will be on the
   *   whole page.
   * @param string|null $listTitle
   *   Label of the list which is expected to contain the tiles. We search for
   *   tiles in the parent element of the list heading if given.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   An array of tiles elements, keyed by tile title.
   */
  protected function getTiles(?string $region = NULL, ?string $listTitle = NULL): array {
    $regionObj = $region
      ? $this->getRegion($region)
      : $this->getSession()->getPage();

    if ($listTitle) {
      $listTitleElements = array_filter(
        $regionObj->findAll('css', 'h1,h2,h3,h4,h5,h6'),
        function (NodeElement $element) use ($listTitle) {
          return trim($element->getText()) === $listTitle;
        }
      );
      $this->assertCondition(
        !empty($listTitleElements),
        sprintf(
          "Cannot find the heading '%s'",
          $listTitle,
        )
      );
      // Pick the first.
      $regionObj = reset($listTitleElements)->getParent();
    }

    $title_selector = implode(', ', [
      '.card-title',
      '.card-title > h1 a',
      'h2 a',
      '.tile__title',
      '.slide__title',
    ]);

    $result = [];
    foreach ($regionObj->findAll('css', $this->getTileCssSelector()) as $element) {
      $title_element = $element->find('css', $title_selector);
      // Some tiles don't have a title, like the one to create a new collection
      // in the collections page.
      if ($title_element) {
        $title = $title_element->getText();
        $result[$title] = $element;
      }
    }
    return $result;
  }

  /**
   * Finds a tile element by its heading.
   *
   * @param string $heading
   *   The heading of the tile to find.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element found.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when the element is not found.
   */
  protected function getTileByHeading(string $heading): NodeElement {
    $selectors = [
      'tile',
      // Allow the cards in the "My subscriptions" page to be selected.
      'group-subscription',
    ];

    foreach ($selectors as $selector) {
      try {
        $element = $this->getListingByHeading($selector, $heading);
      }
      catch (\Exception $e) {
        // The selector is not present on the page.
        continue;
      }

      if ($element) {
        return $element;
      }
    }

    throw new ElementNotFoundException($this->getSession()->getDriver(), "'$heading' tile.");
  }

  /**
   * Finds a list item element by its heading.
   *
   * @param string $type
   *   The class of the element that is searched for.
   * @param string $heading
   *   The heading on the item.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The found node element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when the element is not found.
   */
  protected function getListingByHeading(string $type, string $heading): NodeElement {
    $xpath = '//*[@class and contains(concat(" ", normalize-space(@class), " "), " ' . $type . ' ")][.//h3[normalize-space()="' . $heading . '"]]';
    if (!$element = $this->getSession()->getPage()->find('xpath', $xpath)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), "'$heading' $type item.");
    }
    return $element;
  }

  /**
   * Finds a facet by alias.
   *
   * @param string $alias
   *   The facet alias.
   * @param \Behat\Mink\Element\NodeElement|null $region
   *   (optional) Limit the search to a specific region. If empty, the whole
   *   page will be used. Defaults to NULL.
   * @param string $html_tag
   *   (optional) Limit to a specific html tag when searching for an element.
   *   This can be useful in cases where the data drupal facet id is placed in
   *   more than one html tag e.g. the dropdown has the id placed in both the
   *   <li> tag of links and the <select> element.
   * @param bool $throw_exception
   *   (optional) Whether to throw an exception if the facet is not found.
   *   Defaults to TRUE.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The facet node element.
   *
   * @throws \Exception
   *   Thrown when the facet is not found in the designated area.
   */
  protected function findFacetByAlias(string $alias, ?NodeElement $region = NULL, string $html_tag = '*', bool $throw_exception = TRUE): ?NodeElement {
    if ($region === NULL) {
      $region = $this->getSession()->getPage();
    }
    $facet_id = $this->getFacetIdFromAlias($alias);
    $element = $region->find('xpath', "//{$html_tag}[@data-drupal-facet-id='{$facet_id}']");

    if (!$element && $throw_exception) {
      throw new \Exception("The facet '$alias' was not found in the page.");
    }

    return $element;
  }

  /**
   * Gets the date or time component of a date sub-field in a date range field.
   *
   * @param string $field
   *   The date range field name.
   * @param string $component
   *   The sub-field component. Either "date" or "time".
   * @param string|null $date
   *   (optional) The sub-field name. Either "start" or "end". If left empty, it
   *   is assumed that the field is a simple datetime field and not a range,
   *   thus, the date or time components are looked in the whole field.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The date or time component element.
   *
   * @throws \Exception
   *   Thrown when the date range field is not found.
   */
  protected function findDateRangeComponent(string $field, string $component, ?string $date = NULL): NodeElement {
    /** @var \Behat\Mink\Element\NodeElement $fieldset */
    $fieldset = $this->getSession()->getPage()->find(
      'named',
      ['fieldset', $field]
    );

    if (!$fieldset) {
      throw new \Exception("The '$field' field was not found.");
    }

    if ($date !== NULL) {
      $date = ucfirst($date) . ' date';
      /** @var \Behat\Mink\Element\NodeElement $element */
      $element = $fieldset->find('xpath', '//label[text()="' . $date . '"]//following-sibling::div[1]');
      if (!$element) {
        throw new \Exception("The '$date' sub-field of the '$field' field was not found.");
      }
    }
    else {
      $element = $fieldset;
    }

    $component_node = $element->findField(ucfirst($component));
    if (!$component_node) {
      throw new \Exception("The '$component' component for the '$field' '$element' was not found.");
    }

    return $component_node;
  }

  /**
   * Searches for a field that is disabled.
   *
   * @param string $label
   *   The label of the field.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The date or time component element.
   */
  protected function findDisabledField(string $label): ?NodeElement {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();
    // The *[self::div|self::fieldset] is because ief sets the class 'form-item'
    // in a fieldset rather than a div.
    $element = $page->find('xpath', "//*[self::div|self::fieldset][contains(concat(' ', normalize-space(@class), ' '), ' form-item ') and contains(., '{$label}')]//input[@disabled and @disabled='disabled']");
    if (empty($element)) {
      // Try again to fetch fields with textareas. These are marked as disabled
      // by setting the class 'form-disabled' to the wrapper div and not in the
      // input.
      $element = $page->find('xpath', "//div[(contains(concat(' ', normalize-space(@class), ' '), ' form-disabled ') or contains(concat(' ', normalize-space(@class), ' '), ' form-item--disabled ')) and contains(., '{$label}')]");
    }
    if (!$element) {
      // Try again to find a button.
      $element = $page->findButton($label);
    }
    return $element;
  }

  /**
   * Returns the active links in the page or in a specific region.
   *
   * An "active" link is a link with the class "is-active" or with the class
   * "active-trail", which indicates that it is in the active trail of the
   * current page.
   *
   * @param string|null $region
   *   The region label. If no region is provided, the search will be on the
   *    whole page.
   *
   * @return \Behat\Mink\Element\NodeElement[]|null
   *   An array of node elements matching the search.
   */
  protected function findLinksMarkedAsActive($region = NULL): ?array {
    if ($region === NULL) {
      /** @var \Behat\Mink\Element\DocumentElement $regionObj */
      $regionObj = $this->getSession()->getPage();
    }
    else {
      $regionObj = $this->getRegion($region);
    }

    return $regionObj->findAll('css', 'a.is-active, a.active-trail');
  }

  /**
   * Returns the named element with the given locator, in the given region.
   *
   * Use this to easily locate "named elements" such as buttons, links, fields,
   * checkboxes etc in a given region.
   *
   * For the full list of supported elements, check NamedSelector::$selectors.
   *
   * @param string $locator
   *   The locator that identifies this particular element. This varies by
   *   element type, but it is often a CSS ID, title, text or value that is set
   *   on the element.
   * @param string $element
   *   The element name, e.g. 'fieldset', 'field', 'link', 'button', 'content',
   *   'select', 'checkbox', 'radio', 'file', 'optgroup', 'option', 'table', ...
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region to check in.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element.
   *
   * @throws \Exception
   *   Thrown when the element is not found in the given region.
   *
   * @see \Behat\Mink\Selector\NamedSelector
   */
  protected function findNamedElementInRegion(string $locator, string $element, ?TraversableElement $region = NULL): TraversableElement {
    if (empty($region)) {
      $region = $this->getSession()->getPage();
    }
    $session = $this->getSession();
    $element = $region->find('named', [$element, $locator]);
    if (!$element) {
      throw new \Exception(sprintf('No element with locator "%s" found in the "%s" region on the page %s.', $locator, $region->getXpath(), $session->getCurrentUrl()));
    }
    return $element;
  }

  /**
   * Returns selectors used to find elements with a human readable identifier.
   *
   * @param string $alias
   *   A human readable element identifier.
   *
   * @return array[]
   *   An indexed array of selectors intended to be used with Mink's `find()`
   *   methods. Each value is a tuple containing two strings:
   *   - 0: the selector, e.g. 'css' or 'xpath'.
   *   - 1: the locator.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the element name is not defined.
   */
  protected function getSelectorsMatchingElementAlias(string $alias): array {
    $elements = [
      // The various search input fields.
      [
        'names' => [
          'search',
          'search bar',
          'search bars',
          'search field',
          'search fields',
        ],
        'selectors' => [
          ['css', 'input[name="keys"]'],
        ],
      ],
      [
        'names' => [
          'oss search',
          'oss search bar',
        ],
        'selectors' => [
          ['css', 'input[name="oss_keys"]'],
        ],
      ],
    ];

    foreach ($elements as $element) {
      if (in_array($alias, $element['names'])) {
        return $element['selectors'];
      }
    }

    throw new \InvalidArgumentException("No selectors are defined for the element named '$alias'.");
  }

  /**
   * Returns elements that match the given human readable identifier.
   *
   * @param string $alias
   *   A human-readable element identifier.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The elements matching the identifier.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the element name is not defined.
   */
  protected function getElementsMatchingElementAlias(string $alias): array {
    $elements = [];

    foreach ($this->getSelectorsMatchingElementAlias($alias) as $selector_tuple) {
      [$selector, $locator] = $selector_tuple;
      $elements = array_merge($elements, $this->getSession()
        ->getPage()
        ->findAll($selector, $locator));
    }

    // Do not include hidden elements (e.g. elements that exist in the page but
    // are hidden in certain screen sizes).
    return array_filter($elements, function (TraversableElement $element) {
      return $element->isVisible();
    });
  }

  /**
   * Returns all images found in the given element (or on the entire page).
   *
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   (optional) The element to check in. If omitted the entire page will be
   *   checked.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The images found in the given element or on the page.
   */
  protected function getImages(?NodeElement $element = NULL): array {
    if (empty($element)) {
      $element = $this->getSession()->getPage();
    }
    return $element->findAll('xpath', "//img[@srcset|@src]");
  }

  /**
   * Build a regular expression to find the image in a string.
   *
   * Drupal appends an underscore and a number to the filename when duplicate
   * files are uploaded, for example when a test is run more than once.
   * The XPath and selector version that we are using does not support regular
   * expressions, and we cannot easily search for the file name otherwise.
   * The elements are loaded instead and the regular expression is being run
   * in php.
   *
   * @param string $imageFilename
   *   Name of the image file to build a regular expression for.
   *
   * @return string
   *   The regular expression to find the image file (or its underscored
   *   version).
   */
  protected function getImageNameRegex(string $imageFilename): string {
    static $regexMap;
    if (empty($regexMap[$imageFilename])) {
      $parts = pathinfo($imageFilename);
      $extension = preg_quote($parts['extension'], '/');
      $filename = preg_quote($parts['filename'], '/');
      $regexMap[$imageFilename] = '/\b' . $filename . '(_\d+)?\.' . $extension . '(\?itok=.*)?(\b|$)/';
    }
    return $regexMap[$imageFilename];
  }

  /**
   * Determines whether the provided image element's source is the given file.
   *
   * @param string $filename
   *   The name of the image file.
   * @param \Behat\Mink\Element\NodeElement $imageElement
   *   The <img/> node element to check.
   *
   * @return bool
   *   Whether the provided image element's source is the given file.
   */
  protected function imageSourceMatches(string $filename, NodeElement $imageElement): bool {
    $expression = $this->getImageNameRegex($filename);
    $attributesToCheck = array_filter([
      $imageElement->getAttribute('src'),
      $imageElement->getAttribute('srcset'),
    ]);
    foreach ($attributesToCheck as $source) {
      if (preg_match($expression, $source)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the image node with a given file name if it exists.
   *
   * @param string $filename
   *   The file name.
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   (optional) The element to check in. If omitted the entire page will be
   *   checked.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The matching image element, or NULL if it cannot be found.
   */
  protected function getImage(string $filename, ?NodeElement $element = NULL): NodeElement|null {
    foreach ($this->getImages($element) as $imageElement) {
      if ($this->imageSourceMatches($filename, $imageElement)) {
        return $imageElement;
      }
    }
    return NULL;
  }

  /**
   * Returns the image node with a given file name if it exists.
   *
   * @param string $filename
   *   The file name.
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   (optional) The element to check in. If omitted the entire page will be
   *   checked.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The matching image elements. Empty array if no matching image was found.
   */
  protected function getAllImages(string $filename, ?NodeElement $element = NULL): array {
    $images = $this->getImages($element);
    foreach ($images as $delta => $imageElement) {
      if (!$this->imageSourceMatches($filename, $imageElement)) {
        unset($images[$delta]);
      }
    }
    return $images;
  }

  /**
   * Accurately checks if image with a given file name exists in an element.
   *
   * @param string $filename
   *   The file name.
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   (optional) The element to check in. If omitted the entire page will be
   *   checked.
   *
   * @return bool
   *   Whether the element exists or not in the given element or page.
   */
  protected function hasImage(string $filename, ?NodeElement $element = NULL): bool {
    foreach ($this->getImages($element) as $imageElement) {
      if ($this->imageSourceMatches($filename, $imageElement)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Asserts that the current URL matches the expected one, including the query.
   *
   * Note that \Behat\Mink\WebAssert::addressEquals() strips out the query
   * string, comparing only the path and the fragment. But, in the scope of this
   * test, we need to also compare the query strings.
   *
   * @param \Drupal\Core\Url|string $expected_url
   *   The expected URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Behat\Mink\WebAssert::addressEquals()
   */
  public function assertCurrentUrl(string $expected_url): void {
    // Check first the path & the fragment.
    $this->assertSession()->addressEquals($expected_url);
    // Compare also the query strings as arrays but allow different order.
    $expected_query = $this->normalizeQueryString($expected_url);
    $current_url = $this->getSession()->getCurrentUrl();
    $actual_query = $this->normalizeQueryString($current_url);
    $this->assertSameValues($expected_query, $actual_query, sprintf('Expected the %s url but found %s.', $expected_url, $current_url));
  }

  /**
   * Normalizes a given URL query string to an array.
   *
   * @param string $url
   *   The URL.
   *
   * @return array
   *   The array representation of the query string.
   */
  protected function normalizeQueryString(string $url): array {
    $query_string = mb_convert_encoding((string) parse_url(urldecode($url), PHP_URL_QUERY), 'ISO-8859-1', 'UTF-8');
    parse_str($query_string, $query_array);
    return $query_array;
  }

  /**
   * Returns the tile CSS selector.
   *
   * @return string
   *   The tile CSS selector.
   */
  protected function getTileCssSelector(): string {
    return implode(', ', [
      '.card .card-title',
      'article.tile .tile__title',
      'article.slide',
    ]);
  }

  /**
   * Returns a region CSS selector given its label defined in behat.yml.
   *
   * @param string $label
   *   The region label.
   *
   * @return string
   *   The region's CSS selector.
   *
   * @throws \Exception
   *   When the region with passed label doesn't exist.
   */
  protected function getRegionSelector(string $label): string {
    $regions = $this->getParameter('regions');
    if (!isset($regions[$label])) {
      throw new \Exception("No such region configured: $label");
    }
    return $regions[$label];
  }

  /**
   * Runs the given callback, retrying on Selenium stale element errors.
   *
   * JQuery-driven widgets (autocomplete, AJAX-rendered lists) may rebuild the
   * DOM between fetching an element handle and using it, invalidating the
   * handle mid-call. Re-running the operation with a freshly-fetched handle
   * resolves the race.
   *
   * @param callable $operation
   *   The operation to run. Must throw StaleElementReference on a stale handle
   *   so that this helper can retry it.
   *
   * @return mixed
   *   The return value of $operation.
   */
  protected function retryOnStaleElement(callable $operation): mixed {
    $attempts = 3;
    while (TRUE) {
      try {
        return $operation();
      }
      catch (StaleElementReference $e) {
        if (--$attempts === 0) {
          throw $e;
        }
        usleep(150000);
      }
    }
  }

}
