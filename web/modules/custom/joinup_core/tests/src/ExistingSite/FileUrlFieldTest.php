<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\joinup_core\Traits\TestFileTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\file\FileInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use weitzman\LoginTrait\LoginTrait;

/**
 * Provides methods specifically for testing File module's field handling.
 */
#[Group('joinup_core')]
class FileUrlFieldTest extends JoinupExistingSiteTestBase {

  use TestFileTrait;
  use LoginTrait;
  use StringTranslationTrait;

  /**
   * The helper service that deals with files and stream wrappers.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The RDF storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fileSystem = $this->container->get('file_system');
  }

  /**
   * Tests upload of a file to an file URL field.
   */
  public function testSingleValuedWidgetLocalFile(): void {
    $page = $this->getSession()->getPage();
    $account = $this->createUser([], NULL, FALSE, ['roles' => ['moderator']]);
    $this->drupalLogin($account);

    $solution = $this->createNode([
      'type' => 'solution',
      'title' => $this->randomString(),
      'field_state' => 'published',
      // Ensure a parent collection.
      'og_audience' => $this->createNode([
        'type' => 'collection',
        'title' => $this->randomString(),
        'field_state' => 'published',
      ]),
    ]);
    $licence = $this->createNode([
      'type' => 'licence',
      'title' => $this->randomString(),
    ]);

    $test_file = $this->getTestFile('text');
    $this->assertTrue($test_file instanceof FileInterface);

    $distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
      'field_licence' => $licence->id(),
      'description' => $this->randomString(),
      'field_state' => 'published',
    ]);

    $field_name = 'resources';

    // Test file for new entities.
    $url = "/node/" . $distribution->id() . "/edit";

    $this->drupalGet($url);
    $page->pressButton('Upload file');
    $this->addFileUrlItem($field_name . '_0', 'upload', $test_file->getFileUri());
    $this->submitForm(['title[0][value]' => 'Foo'], 'Update');

    // Check that the file has been uploaded to the file URL field.
    $distribution = Node::load($distribution->id());
    $this->assertTrue($distribution instanceof DistributionInterface);
    $file = $distribution->getResources()[0];
    $this->markEntityForCleanup($file);
    $initial_uri = $file->getFileUri();
    $this->assertFileExists($initial_uri);

    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');

    // Ensure the file can be downloaded.
    $this->drupalGet($file_url_generator->generateAbsoluteString($initial_uri));
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet($distribution->toUrl());
    $this->getSession()->getPage()->clickLink('Edit');
    // Upload the same file again to test if the file is saved in a new location
    // while still keeping the same file basename.
    $this->submitForm([], 'Remove');
  }

  /**
   * Appends a new item to a file URL field.
   *
   * @param string $field_name
   *   The file URL field name.
   * @param string $file_mode
   *   The radio button option value with the file mode ('upload', 'remote').
   * @param string $value
   *   Either a file URI of the local file being uploaded (when $file_mode
   *   equals 'upload') or the remote URL (when $file_mode equals 'remote').
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If the field doesn't exist.
   */
  protected function addFileUrlItem($field_name, $file_mode, $value): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $field_name_html = str_replace('_', '-', $field_name);
    // Narrow the search to the field's wrapper.
    $wrapper = $page->find('xpath', "//div[@data-drupal-selector='edit-{$field_name_html}']");
    if (!$wrapper) {
      throw new ElementNotFoundException($session, $field_name);
    }

    if ($file_mode === 'upload') {
      $file_system = $this->container->get('file_system');
      $wrapper->attachFileToField('files[' . $field_name . '_subform_resource_file_0]', $file_system->realpath($value));
    }
    elseif ($file_mode === 'remote') {
      $wrapper->fillField('Remote file URL', $value);
    }
  }

}
