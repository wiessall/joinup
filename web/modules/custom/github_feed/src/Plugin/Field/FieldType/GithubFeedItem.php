<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\github_feed\GithubFeed;

/**
 * Defines the 'github_feed' field type.
 *
 * @FieldType(
 *   id = "github_feed",
 *   label = @Translation("Github feed"),
 *   category = @Translation("General"),
 *   default_widget = "github_feed",
 *   default_formatter = "github_feed_default"
 * )
 */
class GithubFeedItem extends FieldItemBase implements GithubFeedItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'cache' => 86400,
      'uri_scheme' => 'private://',
      'file_directory' => 'keys',
      'encryption_profile' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $element['cache'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cache lifetime (in seconds)'),
      '#default_value' => $settings['cache'],
      '#description' => $this->t('The cache lifetime for the feed items. Set to -1 to disable caching.'),
    ];

    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $element['uri_scheme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $this->getSetting('uri_scheme'),
      '#description' => $this->t('Select the scheme for the storage of the file. Recommended to be private.'),
    ];

    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[FileItem::class, 'validateDirectory']],
    ];

    $encryption_profiles = \Drupal::getContainer()->get('encrypt.encryption_profile.manager')->getAllEncryptionProfiles();
    $options = [];
    foreach ($encryption_profiles as $encryption_profile) {
      $options[$encryption_profile->id()] = $encryption_profile->label();
    }

    $element['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption profile'),
      '#default_value' => $settings['encryption_profile'],
      '#options' => $options,
      '#description' => $this->t('Select the encryption profile to use for encrypting the file.'),
      '#required' => TRUE,
      '#weight' => 4,
    ];

    return $element;
  }

  /**
   * Returns the decrypted value.
   *
   * @return string
   *   The decrypted value.
   */
  public function getAccessToken(): string {
    $fileId = $this->values['target_id'] ?? NULL;
    if ($fileId) {
      $file = File::load($fileId);
      $fileUri = $file->getFileUri();
      $realPath = \Drupal::getContainer()->get('file_system')->realpath($fileUri);
      if (file_exists($realPath)) {
        $encryptionProfile = $this->getFieldDefinition()->getSetting('encryption_profile');
        $decryptedValue = \Drupal::getContainer()->get('github_feed.encrypt')->decrypt(file_get_contents($realPath), $encryptionProfile);
        return $decryptedValue;
      }
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    // If the new value is empty, skip.
    if (!empty($this->getNewKeyValue())) {
      // If the token has changed, invalidate the cache because it might have
      // changed in order to get a new token.
      $cacheKey = GithubFeed::generateCacheIdFromUrlAndData($this->getRepository(), $this->getType(), $this->getItemNum(), $this->getIncludeComments(), $this->getCommentNum());
      $this->getCacheBin()->invalidate($cacheKey);

      $encryptionProfile = $this->getFieldDefinition()->getSetting('encryption_profile');
      $encryptedValue = \Drupal::getContainer()->get('github_feed.encrypt')->encrypt($this->getNewKeyValue(), $encryptionProfile);

      // Check if there is already a file.
      if ($this->getFileTargetId()) {
        $file = File::load($this->getFileTargetId());
        $fileUri = $file->getFileUri();
        file_put_contents($fileUri, $encryptedValue);
      }
      else {
        $random = new Random();
        $directoryWithScheme = $this->getFieldDefinition()->getSetting('uri_scheme') . '://' . trim($this->getFieldDefinition()->getSetting('file_directory'), '/');
        // Prepare the directory.
        \Drupal::getContainer()->get('file_system')->prepareDirectory($directoryWithScheme, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $fileUri = $directoryWithScheme . '/' . $random->word(32);
        file_put_contents($fileUri, $encryptedValue);
        $file = File::create([
          'uri' => $fileUri,
          'uid' => \Drupal::currentUser()->id(),
          'status' => FileInterface::STATUS_PERMANENT,
        ]);
        $file->save();
      }
      $this->values['target_id'] = $file->id();
    }
    elseif ($this->getRemoveValue()) {
      $fileId = $this->getFileTargetId();
      if ($fileId) {
        $file = File::load($fileId);
        $file->delete();
      }
      $this->values['target_id'] = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->repository) || empty($this->type);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['repository'] = DataDefinition::create('uri')
      ->setLabel(t('Repository'))
      ->setRequired(TRUE);
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'))
      ->setRequired(TRUE);
    $properties['item_num'] = DataDefinition::create('integer')
      ->setLabel(t('Number of items'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'small')
      ->addConstraint('Range', ['min' => 1, 'max' => 10]);
    $properties['include_comments'] = DataDefinition::create('boolean')
      ->setLabel(t('Include comments'))
      ->setRequired(TRUE);
    $properties['comment_num'] = DataDefinition::create('integer')
      ->setLabel(t('Number of comments'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'small')
      ->addConstraint('Range', ['min' => 1, 'max' => 10]);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'repository' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
      'type' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'item_num' => [
        'type' => 'int',
        'size' => 'small',
      ],
      'include_comments' => [
        'type' => 'int',
        'size' => 'tiny',
      ],
      'comment_num' => [
        'type' => 'int',
        'size' => 'small',
      ],
      'target_id' => [
        'description' => 'The ID of the file entity.',
        'type' => 'int',
        'unsigned' => TRUE,
      ],
    ];

    $schema = [
      'columns' => $columns,
      'foreign keys' => [
        'target_id' => [
          'table' => 'file_managed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['repository'] = 'http://github.com/' . $random->word(mt_rand(4, 8)) . '/' . $random->word(mt_rand(4, 8));
    $values['type'] = array_rand(self::allowedTypeValues());
    $values['include_comments'] = (bool) mt_rand(0, 1);
    $values['item_num'] = mt_rand(1, 10);
    $values['comment_num'] = mt_rand(1, 10);
    return $values;
  }

  /**
   * Returns the file contents if exists.
   *
   * @return string
   *   The file contents.
   */
  protected function getNewKeyValue(): string {
    return $this->values['api_key'] ?? '';
  }

  /**
   * Returns the flag for removal of the key.
   *
   * @return bool
   *   The flag for removal of the key.
   */
  protected function getRemoveValue(): bool {
    return $this->values['remove_value'] ?? FALSE;
  }

  /**
   * Returns the repository URL.
   *
   * @return string
   *   The repository URL.
   */
  protected function getRepository(): string {
    return $this->values['repository'] ?? '';
  }

  /**
   * Returns the type of feed.
   *
   * @return string
   *   The type of feed.
   */
  protected function getType(): string {
    return $this->values['type'] ?? '';
  }

  /**
   * Returns the number of items to display.
   *
   * @return int
   *   The number of items to display.
   */
  protected function getItemNum(): int {
    return $this->values['item_num'] ?? 0;
  }

  /**
   * Returns whether to include comments.
   *
   * @return bool
   *   Whether to include comments.
   */
  protected function getIncludeComments(): bool {
    return $this->values['include_comments'] ?? FALSE;
  }

  /**
   * Returns the number of comments to display.
   *
   * @return int
   *   The number of comments to display.
   */
  protected function getCommentNum(): int {
    return $this->values['comment_num'] ?? 0;
  }

  /**
   * Returns the file target ID.
   *
   * @return int|NULL
   *   The file target ID.
   */
  protected function getFileTargetId(): ?int {
    return $this->values['target_id'] ?? NULL;
  }

  /**
   * Returns allowed values for 'type' sub-field.
   *
   * @return array
   *   The list of allowed values.
   */
  public static function allowedTypeValues() {
    return [
      'issue' => t('Issues'),
      'pull_request' => t('Pull requests'),
      'events' => t('Activity stream'),
    ];
  }

  /**
   * Returns the cache used for storing the feed.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend.
   */
  protected function getCacheBin(): CacheBackendInterface {
    return \Drupal::cache('cache.github_feed');
  }

}
