<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\github_feed\Plugin\Field\FieldType\GitHubFeedItem;

/**
 * Defines the 'github_feed' field widget.
 *
 * @FieldWidget(
 *   id = "github_feed",
 *   label = @Translation("GitHub feed"),
 *   field_types = {"github_feed"},
 * )
 */
class GitHubFeedWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['#type'] = 'container';
    $fieldName = $element['#field_parents'][0] . '[' . implode('][', array_slice($element['#field_parents'], 1)) . '][' . $items->getName() . '][' . $delta . ']';

    $element['repository'] = [
      '#type' => 'url',
      '#title' => $this->t('Repository'),
      '#default_value' => $items[$delta]->repository ?? '',
      '#required' => TRUE,
      '#element_validate' => [[static::class, 'validateGitHubRepository']],
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => ['' => $this->t('- Select a value -')] + GitHubFeedItem::allowedTypeValues(),
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

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#element_validate'] = [[static::class, 'validateEmptyValue']];
    return $element;
  }

  /**
   * Validates that the repository is a valid GitHub repository.
   *
   * @param array $element
   *   The element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateGitHubRepository(&$element, FormStateInterface $form_state, array &$complete_form): void {
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $element['#parents']);
    if (!empty($value['repository'])) {
      $url = parse_url($value['repository']);
      $regex = '/^\/[A-Za-z0-9\-]+\/[A-Za-z0-9\-]+(?:\.git)?$/';
      if (!isset($url['host']) || $url['host'] !== 'github.com' || !isset($url['path']) || !preg_match($regex, $url['path'])) {
        $form_state->setError($element, t('The repository must be a valid GitHub repository. The value must be in the format "https://github.com/username/repository" or "https://github.com/username/repository.git".'));
      }
    }
  }

  /**
   * Validates that if there is a repository, there is also a type.
   *
   * @param array $element
   *   The element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateEmptyValue(&$element, FormStateInterface $form_state, array &$complete_form): void {
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $element['#parents']);
    if (!empty($value['repository']) && empty($value['type'])) {
      $form_state->setError($element, t('You must select a type if you have a repository.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $return = [];
    foreach ($values as $delta => $value) {
      $return[$delta]['repository'] = $value['repository'];
      $return[$delta]['type'] = $value['type'];
      $return[$delta]['item_num'] = $value['item_num'];
      $return[$delta]['include_comments'] = $return[$delta]['type'] === 'events' ? '0' : $value['comments_container']['include_comments'];
      $return[$delta]['comment_num'] = $value['comments_container']['comment_num'];
    }
    return $return;
  }

}
