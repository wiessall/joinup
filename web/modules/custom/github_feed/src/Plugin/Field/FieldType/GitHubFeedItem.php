<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'github_feed' field type.
 *
 * @FieldType(
 *   id = "github_feed",
 *   label = @Translation("GitHub feed"),
 *   category = @Translation("General"),
 *   default_widget = "github_feed",
 *   default_formatter = "github_feed_default"
 * )
 */
class GitHubFeedItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      // This will make the page refresh the feed after a day if left as 86400.
      'cache_max_age' => 86400,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $element['cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache lifetime (in seconds)'),
      '#default_value' => $settings['cache_max_age'],
      '#min' => -1,
      '#description' => $this->t('The cache lifetime for the feed items. Set to 0 to disable caching.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->repository) || empty($this->type);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
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
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'repository' => [
          'type' => 'varchar',
          'length' => 256,
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
      ],
      'indexes' => [
        'repository' => ['repository'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
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
    return (bool) $this->values['remove_value'] ?? FALSE;
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
    return (int) $this->values['item_num'] ?? 0;
  }

  /**
   * Returns whether to include comments.
   *
   * @return bool
   *   Whether to include comments.
   */
  protected function getIncludeComments(): bool {
    return (bool) $this->values['include_comments'] ?? FALSE;
  }

  /**
   * Returns the number of comments to display.
   *
   * @return int
   *   The number of comments to display.
   */
  protected function getCommentNum(): int {
    return (int) $this->values['comment_num'] ?? 0;
  }

  /**
   * Returns the file target ID.
   *
   * @return int|null
   *   The file target ID.
   */
  protected function getFileTargetId(): ?int {
    return (int) $this->values['target_id'] ?? NULL;
  }

  /**
   * Returns allowed values for 'type' sub-field.
   *
   * @return array
   *   The list of allowed values.
   */
  public static function allowedTypeValues(): array {
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
    return \Drupal::cache('github_feed');
  }

}
