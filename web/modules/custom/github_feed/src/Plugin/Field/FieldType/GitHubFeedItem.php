<?php

declare(strict_types=1);

namespace Drupal\github_feed\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\github_feed\GitHubFeedInterface;

/**
 * Defines the 'github_feed' field type.
 *
 * @FieldType(
 *   id = "github_feed",
 *   label = @Translation("GitHub feed"),
 *   description = @Translation("Exposes a GitHub feed"),
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
    $properties['comment_num'] = DataDefinition::create('integer')
      ->setLabel(t('Number of comments'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'small')
      ->addConstraint('Range', ['min' => 0, 'max' => 10]);

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
    $values['type'] = array_rand(GitHubFeedInterface::KEYS);
    $values['item_num'] = mt_rand(1, 10);
    $values['comment_num'] = mt_rand(0, 10);
    return $values;
  }

}
