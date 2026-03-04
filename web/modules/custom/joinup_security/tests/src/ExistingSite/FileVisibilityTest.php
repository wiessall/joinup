<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_security\ExistingSite;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\comment\Entity\Comment;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file_visibility\FileVisibility;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\og\Entity\OgRole;
use Drupal\track_usage\Recorder;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests integration with the file_visibility module.
 */
#[Group('joinup_security')]
class FileVisibilityTest extends JoinupExistingSiteTestBase {

  /**
   * Testing user accounts.
   *
   * @var array<string, \Drupal\user\UserInterface>
   */
  protected array $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account['author'] = $this->createUser();
    $this->account['regular'] = $this->createUser();
    $this->account['moderator'] = $this->createUser(values: ['roles' => ['moderator']]);
    $this->account['group_owner'] = $this->createUser();
    $collection = \Drupal::service('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    \Drupal::service('og.membership_manager')->getMembership($collection, $this->account['group_owner']->id())
      ->addRole(OgRole::load('node-collection-administrator'))
      ->addRole(OgRole::load('node-collection-facilitator'))
      ->save();

    // For test clarity, we disable the EU Login 403 redirect.
    // @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginAnonymous403Subscriber::on403()
    \Drupal::state()->set('joinup_eulogin.disable_redirect', TRUE);
  }

  /**
   * Tests a temporary file upload.
   *
   * Spammers often are uploading files without saving the form. The temporary
   * file upload is alive until `system.file:temporary_maximum_age` threshold.
   * If the file URL is publicly reachable, it can be used by spammers.
   */
  public function testTemporaryFile(): void {
    $session = $this->getSession();
    $page = $session->getPage();

    $this->drupalLogin($this->account['author']);

    $collection = \Drupal::service('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    $this->drupalGet($collection->toUrl());
    $this->clickLink('Add news');

    $fileName = strtolower($this->randomMachineName()) . '.txt';
    file_put_contents("temporary://$fileName", $this->randomString());

    $page->attachFileToField('files[field_attachment_0][]', "temporary://$fileName");
    $page->findButton('Upload')->press();

    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $files = $storage->loadByProperties(['filename' => $fileName]);
    $file = reset($files);
    assert($file instanceof FileInterface);
    $this->markEntityForCleanup($file);

    // Check that the temporary file is ONLY accessible for the author.
    // @see \Drupal\file\Element\ManagedFile::valueCallback()
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileNotAccessible($file, $this->account['regular']);
    $this->assertFileNotAccessible($file, $this->account['moderator']);
    $this->assertFileNotAccessible($file, $this->account['group_owner']);
    $this->assertFileNotAccessible($file);
  }

  /**
   * Tests main content.
   *
   * @todo Extend to all other node types.
   */
  public function testMainContent(): void {
    $file = $this->createEmbedFile();

    $solution = $this->createNode([
      'type' => 'solution',
      'og_audience' => \Drupal::service('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI),
      'title' => $this->randomString(),
      'field_state' => 'draft',
      'description' => [
        'value' => $this->randomString() . ' <a href="' . $file->createFileUrl() . '" data-entity-type="file" data-entity-uuid="' . $file->uuid() . '">' . $file->label() . '</a> ' . $this->randomString(),
        'format' => 'content_editor',
      ],
      'uid' => $this->account['author']->id(),
    ]);

    \Drupal::service('og.membership_manager')
      ->createMembership($solution, $this->account['group_owner'])
      ->addRole(OgRole::load('node-solution-administrator'))
      ->addRole(OgRole::load('node-solution-facilitator'))
      ->save();

    // The file is accessible by the author and privileged users.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    // The file is not accessible for a regular and anonymous user.
    $this->assertFileNotAccessible($file, $this->account['regular']);
    $this->assertFileNotAccessible($file);

    $solution->set('field_state', 'published')->save();

    // The file is now accessible for everyone.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    $this->assertFileAccessible($file, $this->account['regular']);
    $this->assertFileAccessible($file);

    \Drupal::entityTypeManager()->getStorage('file')->resetCache();
    $file = File::load($file->id());
    $this->assertStringStartsWith('public://', $file->getFileUri());
    $this->drupalGet($solution->toUrl());
    // Check that the anchor's href was updated.
    $this->assertSession()->responseContains($file->createFileUrl());
  }

  /**
   * Tests file protection for community content.
   */
  public function testCommunityContent(): void {
    $this->doTestCommunityContent('document', 'resources');
    $this->doTestCommunityContent('event', 'field_attachment');
    $this->doTestCommunityContent('news', 'field_attachment');
    $this->doTestCommunityContent('discussion', 'field_attachment');
    $this->doTestDiscussionComments();
  }

  /**
   * Tests file uploads on community content.
   */
  protected function doTestCommunityContent(string $type, string $fieldName): void {
    if ($type == 'document') {
      $file = $this->createFieldFile('paragraph', 'resource_file', 'resource_file');
      $paragraph = $this->createParagraph([
        'type' => 'resource_file',
        'resource_file' => [
            [
              'target_id' => $file->id(),
            ],
        ],
      ]);
      $nodeValues = [
        'type' => $type,
        'og_audience' => \Drupal::service('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI),
        'title' => $this->randomString(),
        'field_state' => 'draft',
        'resources' => ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()],
        'uid' => $this->account['author']->id(),
      ];
    }
    else {
      $file = $this->createFieldFile('node', $type, $fieldName);
      $nodeValues = [
        'type' => $type,
        'og_audience' => \Drupal::service('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI),
        'title' => $this->randomString(),
        'field_state' => 'draft',
        $fieldName => ['target_id' => $file->id(), 'description' => $this->randomString()],
        'uid' => $this->account['author']->id(),
      ];
    }

    $node = $this->createNode($nodeValues);

    // The file is accessible by the author and privileged users.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    // The file is not accessible for a regular and anonymous user.
    $this->assertFileNotAccessible($file, $this->account['regular']);
    $this->assertFileNotAccessible($file);

    $node->set('field_state', 'published')->save();

    // The file is accessible by everyone.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    $this->assertFileAccessible($file, $this->account['regular']);
    $this->assertFileAccessible($file);

    $node->set('field_state', 'proposed')->save();

    // The file is accessible by the author and privileged users.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    // The permanent file is not accessible for a regular and anonymous user.
    $this->assertFileNotAccessible($file, $this->account['regular']);
    $this->assertFileNotAccessible($file);
  }

  /**
   * Tests discussion's comments.
   */
  protected function doTestDiscussionComments(): void {
    $discussion = $this->createNode([
      'type' => 'discussion',
      'og_audience' => \Drupal::service('rdf_sync.mapper')
        ->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI),
      'title' => $this->randomString(),
      'field_state' => 'draft',
      'uid' => $this->account['author']->id(),
    ]);

    $file = $this->createFieldFile('comment', 'reply', 'field_attachment');
    $comment = Comment::create([
      'comment_type' => 'reply',
      'entity_type' => 'node',
      'entity_id' => $discussion->id(),
      'name' => $this->randomString(),
      'subject' => $this->randomString(),
      'field_name' => 'field_replies',
      'field_attachment' => [
        'target_id' => $file->id(),
        'description' => $this->randomString(),
      ],
      'status' => TRUE,
      'uid' => $this->account['author']->id(),
    ]);
    $comment->save();
    $this->markEntityForCleanup($comment);

    // The file is accessible by the author and privileged users.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    // The file is not accessible for a regular and anonymous user.
    $this->assertFileNotAccessible($file, $this->account['regular']);
    $this->assertFileNotAccessible($file);

    $discussion->set('field_state', 'published')->save();

    // The file is accessible by everyone.
    $this->assertFileAccessible($file, $this->account['author']);
    $this->assertFileAccessible($file, $this->account['moderator']);
    $this->assertFileAccessible($file, $this->account['group_owner']);
    $this->assertFileAccessible($file, $this->account['regular']);
    $this->assertFileAccessible($file);
  }

  /**
   * Asserts that a managed file is accessible for the current user.
   *
   * @param \Drupal\file\FileInterface $file
   *   The managed file.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account to be checked. Anonymous, if omitted.
   */
  protected function assertFileAccessible(FileInterface $file, ?AccountInterface $account = NULL): void {
    $this->fileAccessibilityAssertionHelper(TRUE, $file, $account);
  }

  /**
   * Asserts that a managed file is not accessible for the current user.
   *
   * @param \Drupal\file\FileInterface $file
   *   The managed file.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account to be checked. Anonymous, if omitted.
   */
  protected function assertFileNotAccessible(FileInterface $file, ?AccountInterface $account = NULL): void {
    $this->fileAccessibilityAssertionHelper(FALSE, $file, $account);
  }

  /**
   * Provides a helper for file accessibility assertions.
   *
   * @param bool $expectAccessible
   *   TRUE, test the file is accessible and FALSE, test the opposite.
   * @param \Drupal\file\FileInterface $file
   *   The managed file.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account to be checked. Anonymous, if omitted.
   */
  protected function fileAccessibilityAssertionHelper(bool $expectAccessible, FileInterface $file, ?AccountInterface $account): void {
    $this->container->get(Recorder::class)->registerUsageRecords();
    $this->container->get(FileVisibility::class)->doUpdateFileVisibility();

    // In tests, the static cache becomes stale between some operation, because
    // we're running them in the same long request.
    \Drupal::entityTypeManager()->getAccessControlHandler('file')->resetCache();
    \Drupal::entityTypeManager()->getAccessControlHandler('paragraph')->resetCache();

    // Make sure we're checking the latest file version.
    $file = File::load($file->id());

    if (!$account) {
      $this->drupalLogout();
    }
    elseif ($account->id() != \Drupal::currentUser()->id()) {
      $this->drupalLogin($account);
    }

    $message = [];
    if ($file->access('download') !== $expectAccessible) {
      $message[] = $expectAccessible ? "File entity with ID {$file->id()} should be accessible but it's not" :
        "File entity with ID {$file->id()} shouldn't be accessible but it is";
    }

    if ($this->getStatusCode($file) !== ($expectAccessible ? 200 : 403)) {
      $message[] = $expectAccessible ? "File '{$file->getFilename()}' should be downloadable but it's not" :
        "File '{$file->getFilename()}' shouldn't be downloadable but it is";
    }

    if ($message) {
      $this->fail(implode("\n", $message));
    }
  }

  /**
   * Returns the response of a GET HTTP request to a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return int
   *   The HTTP response status code.
   */
  protected function getStatusCode(FileInterface $file): int {
    $this->drupalGet($file->createFileUrl());
    return $this->getSession()->getStatusCode();
  }

  /**
   * Creates a testing-managed file to be attached to a field.
   *
   * @param string $entityTypeId
   *   The field's host entity type.
   * @param string $bundle
   *   The field's host entity bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return \Drupal\file\FileInterface
   *   A managed file entity.
   */
  protected function createFieldFile(string $entityTypeId, string $bundle, string $fieldName): FileInterface {
    $fieldConfig = FieldConfig::loadByName($entityTypeId, $bundle, $fieldName);
    $uri = $fieldConfig->getSetting('uri_scheme') . '://' . $fieldConfig->getSetting('file_directory');
    $uri = \Drupal::token()->replace($uri);
    return $this->createFile($uri);
  }

  /**
   * Creates a text embedded file entity.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   */
  protected function createEmbedFile(): FileInterface {
    $editor = Editor::load('content_editor');
    $settings = $editor->getSettings()['plugins']['editor_file_file'];
    return $this->createFile($settings['scheme'] . '://' . $settings['directory']);
  }

  /**
   * Creates a file on a given location.
   *
   * @param string $uri
   *   The file location URI.
   *
   * @return \Drupal\file\FileInterface
   *   The created file entity.
   */
  protected function createFile(string $uri): FileInterface {
    \Drupal::service('file_system')->prepareDirectory($uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $uri .= '/' . strtolower($this->randomMachineName()) . '.txt';
    file_put_contents($uri, $this->randomString());

    $file = File::create(['uri' => $uri, 'status' => TRUE]);
    $file->save();
    $this->markEntityForCleanup($file);

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    \Drupal::state()->delete('joinup_eulogin.disable_redirect');
    parent::tearDown();
  }

}
