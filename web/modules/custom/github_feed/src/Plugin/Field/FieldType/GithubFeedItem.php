<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
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
 *   label = @Translation("Github feed"),
 *   category = @Translation("General"),
 *   default_widget = "github_feed",
 *   default_formatter = "github_feed_default"
 * )
 */
class GithubFeedItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = ['cache' => -1];
    return $settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $element['cache'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache'),
      '#options' => [
        -1 => $this->t('No cache'),
        0 => $this->t('Default'),
        60 => $this->t('1 minute'),
        300 => $this->t('5 minutes'),
        900 => $this->t('15 minutes'),
        1800 => $this->t('30 minutes'),
        3600 => $this->t('1 hour'),
        10800 => $this->t('3 hours'),
        21600 => $this->t('6 hours'),
        43200 => $this->t('12 hours'),
        86400 => $this->t('1 day'),
        172800 => $this->t('2 days'),
        604800 => $this->t('1 week'),
        1209600 => $this->t('2 weeks'),
        2419200 => $this->t('4 weeks'),
      ],
      '#default_value' => $settings['cache'],
      '#description' => $this->t('The cache lifetime for the feed items. Set to -1 to disable caching.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->repository) || empty($this->type) || empty($this->item_num);
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
    $properties['include_comments'] = DataDefinition::create('boolean')
      ->setLabel(t('Include comments'))
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
      'include_comments' => [
        'type' => 'int',
        'size' => 'tiny',
      ],
      'item_num' => [
        'type' => 'int',
        'size' => 'small',
      ],
      'comment_num' => [
        'type' => 'int',
        'size' => 'small',
      ],
    ];

    $schema = [
      'columns' => $columns,
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

}
