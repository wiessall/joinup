<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\file\FileInterface;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Entity\DownloadEvent;
use Drupal\link\LinkItemInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Behat step definitions for testing distributions.
 */
class DistributionContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use FileTrait;
  use JavascriptTrait;

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
   */
  #[Then('I should see the :link_type link in the :distribution distribution')]
  public function assertLinkPresentInCompactDistributionTile(string $link_type, string $distribution): void {
    if (!in_array($link_type, ['download', 'external'])) {
      throw new \InvalidArgumentException('Only "download" and "external" values are allowed for $link_type.');
    }

    $distribution = $this->getEntityByLabel('node', $distribution);
    if (!$distribution instanceof DistributionInterface) {
      return;
    }

    $resource = $distribution->getResources()[0];
    if (empty($resource)) {
      throw new \Exception(sprintf('Distribution "%s" does not contain any download links.', $distribution->label()));
    }

    $link = match (TRUE) {
      $resource instanceof FileInterface => \Drupal::service('file_url_generator')->generateAbsoluteString($resource->getFileUri()),
      $resource instanceof LinkItemInterface => \Drupal::service('file_url_generator')->generateAbsoluteString($resource->uri),
    };

    $result = $this->getSession()
      ->getPage()
      ->find('xpath', "//a[contains(., '{$distribution->label()}')]/ancestor::li[contains(concat(' ', @class, ' '), ' releases__item ')]//a[@href='{$link}']");
    if (empty($result)) {
      throw new \Exception(sprintf('No link was found in the "%s" distribution on the page %s', $distribution->label(), $this->getSession()
        ->getCurrentUrl()));
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
   */
  #[Then('the :distribution distribution should have the link of the :file_name in the access URL field')]
  public function assertLinkSynchronized(string $distribution, string $file_name): void {
    $distribution = $this->getEntityByLabel('node', $distribution);
    if (!$distribution instanceof DistributionInterface) {
      return;
    }
    $resources = $distribution->getResources();
    $file = $resources[0];
    if (($file->getFilename() !== $file_name) || !\Drupal::service('file_url_generator')
      ->generateAbsoluteString($file->getFileUri())) {
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
    if (!empty($file_type) && $file_type->get('mime_type')->value !== $mime_type) {
      throw new \Exception("File '$file_name' has the format '{$file_type->get('mime_type')->value}'. Expected '$mime_type'.");
    }
    $expected_file_size = filesize($path);

    // Test the file size.
    if ($expected_file_size !== (int) $file->filesize->value) {
      throw new \Exception("File '$file_name' has the size '{$file->filesize->value}'. Expected '$expected_file_size'.");
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
   */
  #[When('I click :link in the :heading distribution')]
  public function clickLinkInCompactDistribution(string $link, string $heading): void {
    $element = $this->getCompactDistributionByHeading($heading);

    if (!$element) {
      throw new \Exception("The tile '$heading' was not found on the page.");
    }

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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the download event entity could not be saved.
   */
  #[Given('the following distribution download events:')]
  public function downloadEvents(TableNode $table): void {
    foreach ($table->getColumnsHash() as $row) {
      $distribution = $this->getEntityByLabel('node', $row['distribution'], 'distribution');
      $resources = $distribution->getResources();
      $file = reset($resources);
      assert($file instanceof FileInterface, 'The distribution must have at least one file resource.');

      $account = \Drupal::service('email.validator')
        ->isValid($row['user']) ? new AnonymousUserSession() : user_load_by_name($row['user']);
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
   */
  #[Then('I should see the following download entries:')]
  public function assertDownloadEventEntries(TableNode $table): void {
    $base_selector = '.distribution-download-table table > tbody > tr';
    $count = 0;
    foreach ($table->getColumnsHash() as $entry) {
      $count++;
      $selector = $base_selector . ":nth-child({$count})";

      $this->assertSession()
        ->elementTextContains('css', $selector, $entry['user']);
      $this->assertSession()
        ->elementTextContains('css', $selector, $entry['e-mail']);
      $this->getSession()
        ->getPage()
        ->find('css', $selector)
        ->hasLink($entry['distribution']);
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
