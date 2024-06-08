<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\file_url\FileUrlHandler;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Entity\DownloadEvent;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;

/**
 * Behat step definitions for testing distributions.
 */
class DistributionContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use FileTrait;
  use RdfEntityTrait;
  use TestingEntitiesTrait;
  use JavascriptTrait;

  /**
   * Navigates to the canonical page display of a distribution.
   *
   * @param string $distribution
   *   The title of the distribution.
   *
   * @When I go to (the homepage of )the :distribution distribution
   * @When I visit (the homepage of )the :distribution distribution
   * @When I go to (the homepage of )the :distribution distribution
   * @When I visit (the homepage of )the :distribution distribution
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitDistribution(string $distribution): void {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntityByLabel('rdf_entity', $distribution);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Creates a number of distributions with data provided in a table.
   *
   * @param \Behat\Gherkin\Node\TableNode $distribution_table
   *   The distribution data.
   *
   * @codingStandardsIgnoreStart
   *   Table format:
   *   | uri                               | title            | description                | access url                               | creation date     | modification date | parent        |
   *   | http://joinup.eu/distribution/foo | Foo distribution | This is a foo distribution | test.zip                                 | 28-01-1995 12:05  | 30-01-1995 10:44  | Solution name |
   *   | http://joinup.eu/distribution/bar | Bar distribution | This is a bar distribution | http://external_url.com/path-to-file.zip | 28-01-1995 12:06  |                   | Release name  |
   * @codingStandardsIgnoreEnd
   *
   * Fields title and description are mandatory. Field 'access url' can be
   * either an external valid URL or a local file to be uploaded.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )distributions:
   */
  public function givenDistributions(TableNode $distribution_table): void {
    foreach ($distribution_table->getColumnsHash() as $values) {
      $this->createDistributionFromAliasedTableValues($values);
    }
  }

  /**
   * Creates a distribution with data provided in a table.
   *
   * Table format:
   * | title       | Sample distribution                             |
   * | uri         | http://joinup.eu/distribution/foobar            |
   * | description | A sample distribution                           |
   * | access url  | http://external.path/to/file.zip  (or test.zip) |
   * | parent      | Release or solution name                        |
   *
   * Fields 'title' and 'description' are required. Field 'access url' can be
   * either an external valid URL or a local file to be uploaded.
   *
   * @param \Behat\Gherkin\Node\TableNode $distribution_table
   *   The distribution data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )distribution:
   */
  public function givenAssetDistribution(TableNode $distribution_table): void {
    $values = $distribution_table->getRowsHash();
    $this->createDistributionFromAliasedTableValues($values);
  }

  /**
   * Creates a distribution from the given property and field data.
   *
   * @param array $values
   *   An optional associative array of values, keyed by property name.
   *
   * @return \Drupal\joinup_distribution\Entity\DistributionInterface
   *   A new distribution entity.
   *
   * @throws \Exception
   *   Thrown when a given file is not found.
   */
  protected function createDistribution(array $values): DistributionInterface {
    // The Access URL field is either an external URI, or file upload.
    if (!empty($values['access_url'])) {
      global $base_url;
      foreach ($values['access_url'] as $key => $url) {
        if (!UrlHelper::isValid($url, TRUE) || !UrlHelper::isExternal($url) || UrlHelper::externalIsLocal($url, $base_url)) {
          // It's a local file to be uploaded.
          $values['access_url'][$key] = FileUrlHandler::fileToUrl($this->createFile($url));
        }
      }
    }

    // Downloads are stored in a meta entity.
    if (array_key_exists('downloads', $values)) {
      $values['download_count'] = MetaEntity::create([
        'type' => 'download_count',
        'count' => (int) $values['downloads'],
      ]);
      unset($values['downloads']);
    }

    $values['type'] = 'distribution';

    $distribution = Node::create($values);
    $distribution->save();
    $this->entities['node'][$distribution->id()] = $distribution;

    return $distribution;
  }

  /**
   * Creates a distribution using the provided aliases table values.
   *
   * @param array $aliased_values
   *   An associative array of values used to create the distribution, with keys
   *   using the aliases from self::assetDistributionFieldAliases(). If the key
   *   'parent' is present, a relation with a parent solution or release with
   *   a label matching this value will be created.
   *
   * @throws \InvalidArgumentException
   *   Thrown when an unknown key alias is present in the list of values.
   * @throws \Exception
   *   Thrown when the passed values cannot be converted, or when the
   *   distribution cannot be created.
   */
  protected function createDistributionFromAliasedTableValues(array $aliased_values): void {
    $aliases = self::distributionFieldAliases();

    $values = [];
    // Replace the column aliases with the actual field names.
    foreach ($aliased_values as $key => $value) {
      if (array_key_exists($key, $aliases)) {
        $values[$aliases[$key]] = $value;
      }
      else {
        throw new \InvalidArgumentException("Unknown key '$key' in asset distribution table.");
      }
    }

    if (isset($values['parent'])) {
      $values['parent'] = [$values['parent']];
    }

    $values = $this->convertEntityReferencesValues('node', 'distribution', $values);
    $this->createDistribution($values);
  }

  /**
   * Deletes a distribution.
   *
   * @param string $distribution
   *   The title of the distribution.
   *
   * @When I delete the :distribution distribution
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity could not be deleted.
   */
  public function deleteDistribution(string $distribution): void {
    /** @var \Drupal\joinup_distribution\Entity\DistributionInterface $entity */
    $entity = $this->getEntityByLabel('node', $distribution);
    if (isset($this->entities['node'][$entity->id()])) {
      unset($this->entities['node'][$entity->id()]);
    }
    $entity->skip_notification = TRUE;
    $entity->delete();
  }

  /**
   * Checks the number of available distributions.
   *
   * @param int $number
   *   The expected number of distributions.
   *
   * @throws \Exception
   *   Thrown if the count does not match the expected number.
   *
   * @Then I should have :number distribution(s)
   */
  public function assertAssetDistributionCount(int $number): void {
    $this->assertEntityCount('rdf_entity', 'asset_distribution', $number);
  }

  /**
   * Field alias mapping.
   *
   * @return array
   *   Mapping.
   */
  protected static function distributionFieldAliases(): array {
    // Mapping alias - field name.
    return [
      'access url' => 'access_url',
      'creation date' => 'created',
      'description' => 'description',
      'downloads' => 'downloads',
      'file size' => 'file_size',
      'format' => 'format',
      'licence' => 'licence',
      'modification date' => 'changed',
      'representation technique' => 'representation_technique',
      'parent' => 'parent',
      'state' => 'field_state',
      'status' => 'process_status',
      'title' => 'title',
      'uri' => 'uri',
    ];
  }

  /**
   * Checks if a distribution in compact view contains download links.
   *
   * The compact view is used for example in the "Downloads" page.
   * This currently only checks the first download link.
   *
   * @param string $link_type
   *   Whether to look for the "Download" button or the "External" link button.
   *   'download' and 'external' are allowed.
   * @param string $distribution
   *   The name of the distribution.
   *
   * @throws \Exception
   *   Thrown if the download link cannot be found.
   * @throws \InvalidArgumentException
   *   Thrown if the link type parameter does not receive a valid value.
   *
   * @Then I should see the :link_type link in the :distribution distribution
   */
  public function assertLinkPresentInCompactDistributionTile(string $link_type, string $distribution): void {
    if (!in_array($link_type, ['download', 'external'])) {
      throw new \InvalidArgumentException('Only "download" and "external" values are allowed for $link_type.');
    }
    $link_type = ucfirst($link_type);
    $distribution = $this->getEntityByLabel('node', $distribution);
    if (empty($distribution->access_url->first())) {
      throw new \Exception(sprintf('Distribution "%s" does not contain any download links.', $distribution->label()));
    }
    $file = FileUrlHandler::urlToFile($distribution->access_url->target_id);
    $link = FileUrlHandler::isRemote($file) ? $distribution->access_url->target_id : \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
    $result = $this->getSession()->getPage()->find('xpath', "//a[contains(., '{$distribution->label()}')]/ancestor::li[contains(concat(' ', @class, ' '), ' releases__item ')]//a[@href='{$link}' and contains(concat(' ', @class, ' '), ' btn ') and normalize-space(text()) = '{$link_type}']");
    if (empty($result)) {
      throw new \Exception(sprintf('No link was found in the "%s" distribution on the page %s', $distribution->label(), $this->getSession()->getCurrentUrl()));
    }
  }

  /**
   * Checks that a distribution in compact view does not contain download links.
   *
   * The compact view is used for example in the "Downloads" page.
   *
   * @param string $distribution
   *   The name of the distribution.
   *
   * @throws \Exception
   *   Thrown if the link was found.
   *
   * @Then the :distribution distribution should not have any download urls
   */
  public function assertLinkNotPresentInCompactDistributionTile(string $distribution): void {
    $link = $this->getSession()->getPage()->find('xpath', "//a[contains(text(), '$distribution')]/ancestor::div[contains(concat(' ', @class, ' '), ' releases__item ')]//a[contains(text(), 'External') or contains(text(), 'Download')]");
    if (!empty($link)) {
      throw new \Exception(sprintf('Distribution "%s" contains download link(s).', $distribution));
    }
  }

  /**
   * Asserts that a file is attached to a distribution.
   *
   * @param string $distribution
   *   The distribution title.
   * @param string $file_name
   *   The file name.
   *
   * @throws \Exception
   *    Thrown when the file or the link is not found.
   *
   * @Then the :distribution distribution should have the link of the :file_name in the access URL field
   */
  public function assertLinkSynchronized(string $distribution, string $file_name): void {
    $distribution = $this->getEntityByLabel('node', $distribution);
    $file = FileUrlHandler::urlToFile($distribution->access_url->target_id);
    if (($file->getFilename() !== $file_name) || !\Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri())) {
      throw new \Exception("The file {$file_name} was not found in the '{$distribution->label()}' distribution'");
    }

    $files_path = $this->getMinkParameter('files_path');
    $path = rtrim(realpath($files_path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
    if (!is_file($path)) {
      throw new \Exception("File '$file_name' was not found in file path '$files_path'.");
    }

    /** @var \Symfony\Component\Mime\MimeTypesInterface $mime_guesser */
    $mime_guesser = \Drupal::service('file.mime_type.guesser');
    $mime_type = $mime_guesser->guessMimeType($path);
    /** @var \Drupal\taxonomy\TermInterface $file_type */
    $file_type = $distribution->get('field_ad_format')->entity;
    // Test the file format.
    if ($file_type->get('mime_type')->value !== $mime_type) {
      throw new \Exception("File '$file_name' has the format '{$file_type->get('mime_type')->value}'. Expected '$mime_type'.");
    }
    $expected_file_size = filesize($path);

    // Test the file size.
    if ($expected_file_size !== (int) $distribution->file_size->value) {
      throw new \Exception("File '$file_name' has the size '{$distribution->file_size->value}'. Expected '$expected_file_size'.");
    }
  }

  /**
   * Uploads a file or sets a remote URL to a 'file_url' field.
   *
   * @param string $file_or_url
   *   Either file name to be uploaded or a remote URL.
   * @param string $file_url_field
   *   The file URL field label.
   *
   * @throws \Exception
   *   The file to be uploaded doesn't exists.
   *
   * @Given I upload the file :file_or_url to :file_url_field
   * @Given I set a remote URL :file_or_url to :file_url_field
   */
  public function addFileUrlItem(string $file_or_url, string $file_url_field): void {
    $type = UrlHelper::isExternal($file_or_url) ? 'remote' : 'upload';

    $session = $this->getSession();
    $page = $session->getPage();

    // Locate the file URL field widget wrapper.
    $wrapper = $page->find('xpath', "//label[text()='$file_url_field']/ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' field--type-file-url ')][1]");
    if (!$wrapper) {
      throw new \Exception("Cannot find the file URL field '$file_url_field'.");
    }

    /** @var \Behat\Mink\Element\NodeElement $radio */
    $radio = $wrapper->find('xpath', "//input[@type='radio']");
    if (!$radio) {
      throw new \Exception("Malformed file URL field '$file_url_field': Can't find the inner radio option.");
    }
    $page->selectFieldOption($radio->getAttribute('name'), $type);

    if ($type === 'upload') {
      // Qualify the file path.
      $files_path = $this->getMinkParameter('files_path');
      $path = rtrim(realpath($files_path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_or_url;
      if (!is_file($path)) {
        throw new \Exception("File '$file_or_url' was not found in file path '$files_path'.");
      }
      $wrapper->attachFileToField('Choose a file', $path);
    }
    elseif ($type === 'remote') {
      $wrapper->fillField('Remote URL', $file_or_url);
    }
  }

  /**
   * Clicks a link inside a distribution rendered with the compact view mode.
   *
   * @param string $link
   *   The text of the link to click.
   * @param string $heading
   *   The distribution title.
   *
   * @throws \Exception
   *   Thrown when the rendered distribution is not found.
   *
   * @When I click :link in the :heading distribution
   */
  public function clickLinkInCompactDistribution(string $link, string $heading): void {
    $element = $this->getCompactDistributionByHeading($heading);

    if (!$element) {
      throw new \Exception("The tile '$heading' was not found on the page.");
    }

    $this->scrollElementIntoView($element);
    $element->clickLink($link);
  }

  /**
   * Creates distribution download events.
   *
   * Table format:
   *
   * @codingStandardsIgnoreStart
   * | user               | distribution |
   * | Anonymous          | Distro one   |
   * | Nickolas Underwood | Distro two   |
   * @codingStandardsIgnoreEnd
   * If the user is a valid email, it's considered to be an anonymous user.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The entries list.
   *
   * @Given the following distribution download events:
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the download event entity could not be saved.
   */
  public function downloadEvents(TableNode $table): void {
    foreach ($table->getColumnsHash() as $row) {
      /** @var \Drupal\asset_distribution\Entity\AssetDistributionInterface $distribution */
      $distribution = $this->getEntityByLabel('node', $row['distribution'], 'distribution');
      $file = FileUrlHandler::urlToFile($distribution->get('access_url')->target_id);
      $account = \Drupal::service('email.validator')->isValid($row['user']) ? new AnonymousUserSession() : user_load_by_name($row['user']);
      $mail = $account->isAnonymous() ? $row['user'] : $account->getEmail();

      $parent = $distribution->getParent();
      $entity = DownloadEvent::create([
        'uid' => $account->id(),
        'mail' => $mail,
        'file' => $file->id(),
        'parent_entity_type' => $parent->getEntityTypeId(),
        'parent_entity_id' => $parent->id(),
      ]);
      $entity->save();
      $this->entities['download_event'][$entity->id()] = $entity;
    }

  }

  /**
   * Asserts the entries in the download event table.
   *
   * Table format:
   *
   * @codingStandardsIgnoreStart
   * | user                     | e-mail                | distribution |
   * | Anonymous (not verified) | mail1@example.com     | Distro one   |
   * | Nickolas Underwood       | underwood@example.com | Distro two   |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The entries list.
   *
   * @throws \Behat\Mink\Exception\ElementTextException
   *   Thrown when one or more of the download events was not present.
   *
   * @Then I should see the following download entries:
   */
  public function assertDownloadEventEntries(TableNode $table): void {
    $base_selector = '.distribution-download-table table > tbody > tr';
    $count = 0;
    foreach ($table->getColumnsHash() as $entry) {
      $count++;
      $selector = $base_selector . ":nth-child({$count})";

      $this->assertSession()->elementTextContains('css', $selector, $entry['user']);
      $this->assertSession()->elementTextContains('css', $selector, $entry['e-mail']);
      $this->getSession()->getPage()->find('css', $selector)->hasLink($entry['distribution']);
    }
  }

  /**
   * Finds a distribution rendered with the compact view mode by its heading.
   *
   * @param string $heading
   *   The heading of the distribution to find.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The compact distribution element, or null if not found.
   */
  protected function getCompactDistributionByHeading(string $heading): ?NodeElement {
    // Locate all the elements.
    $xpath = '//*[@class and contains(concat(" ", normalize-space(@class), " "), " releases__item ")]';
    // That have a heading with the specified text.
    $xpath .= '[.//article[@class and contains(concat(" ", normalize-space(@class), " "), " tile ")]//h1//a[normalize-space()="' . $heading . '"]]';

    return $this->getSession()->getPage()->find('xpath', $xpath);
  }

}
