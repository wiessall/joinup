<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\file_url\FileUrlHandler;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup_distribution\Entity\DownloadEvent;
use Drupal\joinup_release\Entity\Release;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelperInterface;

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
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitDistribution(string $distribution): void {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntityByLabel('node', $distribution);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Distribution specific alterations before creation.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $event
   *   The before node create event.
   *
   * @BeforeNodeCreate
   */
  public function beforeNodeCreateDistributionFields(BeforeNodeCreateScope $event): void {
    $node = $event->getEntity();
    if ($node->type !== 'distribution') {
      return;
    }

    // The Access URL field is either an external URI, or file upload. If it is
    // a local file, translate the value to a URL.
    if (!empty($node->{'access url'}) && (filter_var($node->{'access url'}, FILTER_VALIDATE_URL) === FALSE)) {
      $node->{'access url'} = FileUrlHandler::fileToUrl($this->createFile($node->{'access url'}));
    }

    // Downloads are stored in a meta entity.
    if (!empty($node->downloads)) {
      $node->download_count = MetaEntity::create([
        'type' => 'download_count',
        'count' => (int) $node->downloads,
      ]);
      unset($node->downloads);
    }
  }

  /**
   * AfterNodeCreate method to handle distribution parent.
   *
   * @todo Drop this once the reference target type is fixed in ISAICP-8572.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope $event
   *   The after node create event.
   *
   * @AfterNodeCreate
   */
  public function afterNodeCreateDistributionParent(AfterNodeCreateScope $event): void {
    $node = $event->getEntity();
    if ($node->type !== 'distribution') {
      return;
    }

    $distribution = Node::load($node->nid);

    // @todo Cleanup in ISAICP-8572.
    if (!empty($node->release)) {
      // The parent is a release, thus, get the solution instead.
      $distribution->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, Release::load($node->release[0])->getGroup()->id());
    }
    elseif (empty($node->og_audience)) {
      throw new \InvalidArgumentException("A distribution should have, either a 'release', or a 'solution' field");
    }

    // Keep the update date during the update.
    $distribution->setNewRevision(FALSE);
    // Temporary workaround to not change the created and changed date when they
    // are set in the distribution. The +1 is because in
    // \Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem::preSave() the
    // changed date is set to REQUEST_TIME if it is the same as the original
    // value.
    foreach (['created', 'changed'] as $field) {
      if (isset($node->{$field})) {
        $distribution->set($field, $node->{$field} + 1);
      }
    }
    $distribution->save();
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
   *
   * @todo Do we still need this?
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
    $this->assertEntityCount('node', 'distribution', $number);
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
    $file_type = $distribution->get('format')->entity;
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
