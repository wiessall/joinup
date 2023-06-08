<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\github_feed\Plugin\Field\FieldType\GithubFeedItem;

/**
 * Defines the 'github_feed' field widget.
 *
 * @FieldWidget(
 *   id = "github_feed",
 *   label = @Translation("Github feed"),
 *   field_types = {"github_feed"},
 * )
 */
class GithubFeedWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'keys_directory' => 'private://',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['keys_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory'),
      '#default_value' => $this->getSetting('directory'),
      '#description' => $this->t('The directory inside the files directory where the file will be uploaded. Should be in the private:// scheme.'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $directory = $this->getSetting('directory');
    if ($directory) {
      $summary[] = $this->t('Directory: @directory', ['@directory' => $directory]);
    }
    else {
      $summary[] = $this->t('No directory specified');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['#type'] = 'container';

    // Handle the API key field in a separate container.
    $description = $this->t('The file will be uploaded to the private files directory <strong>and the value will be encrypted</strong>.');
    if (!empty($items->target_id)) {
      $description .= $this->t('<br /><strong>Warning: Leave empty to keep the value. Setting a new value will replace the key. Old keys cannot be recovered.</strong>');
    }
    $description .= $this->t('<br />To generate a new key, go to your <a href=":url">Github settings</a>.', [':url' => 'https://github.com/settings/tokens/new']);

    // Construct the field name by the parents of the element.
    $fieldName = $element['#field_parents'][0] . '[' . implode('][', array_slice($element['#field_parents'], 1)) . '][' . $items->getName() . '][' . $delta . ']';

    $element['key_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['encrypted-file-widget'],
      ],
      '#title' => $this->t('API key'),
    ];

    $element['key_container']['file_contents'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#description' => $description,
      '#default_value' => '',
    ];

    $element['key_container']['remove_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove value'),
      '#description' => $this->t('Remove the value from the field. This will not remove the file.'),
      '#default_value' => FALSE,
    ];

    $element['repository'] = [
      '#type' => 'url',
      '#title' => $this->t('Repository'),
      '#default_value' => $items[$delta]->repository ?? '',
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => ['' => $this->t('- Select a value -')] + GithubFeedItem::allowedTypeValues(),
      '#default_value' => $items[$delta]->type ?? 'issue',
    ];

    $element['item_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of items'),
      '#default_value' => $items[$delta]->item_num ?? 5,
    ];

    $element['comments_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['comments-container'],
      ],
      // 'events' do not have comments.
      '#states' => [
        'invisible' => [
          ':input[name="' . $fieldName . '[type]"]' => [
            'value' => 'events',
          ],
        ],
      ],
    ];

    $element['comments_container']['include_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include comments'),
      '#default_value' => $items[$delta]->include_comments ?? FALSE,
    ];

    $element['comments_container']['comment_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of comments'),
      '#default_value' => $items[$delta]->comment_num ?? 5,
      // Do not show the field if comments are not included or if the type is
      // 'events'.
      '#states' => [
        'visible' => [
          ':input[name="' . $fieldName . '[comments_container][include_comments]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Set a readonly value for the target_id.
    $element['target_id'] = [
      '#type' => 'value',
      '#value' => $items[$delta]->target_id ?? '',
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#element_validate'] = [[static::class, 'validateEmptyValue']];
    return $element;
  }

  /**
   * Validates that if there is a repository, there is also a type.
   */
  public static function validateEmptyValue(&$element, FormStateInterface $form_state, &$complete_form) {
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $element['#parents']);
    if (!empty($value['repository']) && empty($value['type'])) {
      $form_state->setError($element, t('You must select a type if you have a repository.'));
    }

    // File contents cannot be filled while remove value is checked.
    if (!empty($value['key_container']['remove_value']) && !empty($value['key_container']['file_contents'])) {
      $form_state->setError($element, t('You cannot fill the new value for the access token while remove value is checked.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $return = [];
    foreach ($values as $delta => $value) {
      $return[$delta]['file_contents'] = $value['key_container']['file_contents'];
      $return[$delta]['remove_value'] = $value['key_container']['remove_value'];
      $return[$delta]['repository'] = $value['repository'];
      $return[$delta]['type'] = $value['type'];
      $return[$delta]['item_num'] = $value['item_num'];
      $return[$delta]['include_comments'] = $value['comments_container']['include_comments'];
      $return[$delta]['comment_num'] = $value['comments_container']['comment_num'];
      $return[$delta]['target_id'] = $value['target_id'];
    }
    return $return;
  }

}
