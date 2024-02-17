<?php

declare(strict_types=1);

namespace Drupal\github_feed\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\github_feed\GitHubFeedInterface;

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
    $fieldNameBase = empty($element['#field_parents']) ?
      $items->getName() . '[' . $delta . ']' :
      $element['#field_parents'][0] . '[' . implode('][', array_slice($element['#field_parents'], 1)) . '][' . $items->getName() . '][' . $delta . ']';

    $element['repository'] = [
      '#type' => 'url',
      '#title' => $this->t('Repository'),
      '#default_value' => $items[$delta]->repository ?? '',
      '#description' => $this->t('The GitHub repository to use e.g. "https://github.com/username/repository" or "https://github.com/username/repository.git".'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#element_validate' => [[static::class, 'validateGitHubRepository']],
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => ['' => $this->t('- Select a value -')] + GitHubFeedInterface::KEYS,
      '#required' => TRUE,
      '#default_value' => $items[$delta]->type ?? 'issue',
    ];

    $element['item_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of items'),
      '#default_value' => $items[$delta]->item_num ?? 5,
    ];

    $element['comment_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of comments'),
      '#description' => $this->t('The number of comments to show for each item. Set this to 0 to disable comments.'),
      '#default_value' => $items[$delta]->comment_num ?? 0,
      // Do not show the field if the type is 'events'.
      '#states' => [
        'invisible' => [
          ':input[name="' . $fieldNameBase . '[type]"]' => [
            'value' => 'events',
          ],
        ],
      ],
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    return $element;
  }

  /**
   * Validates that the repository is a valid GitHub repository.
   *
   * @param array $element
   *   The element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateGitHubRepository(array &$element, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $element['#parents']);
    if (!empty($value)) {
      $url = parse_url($value);
      $regex = '/^\/[A-Za-z0-9_\-]+\/[A-Za-z0-9_\-]+(?:\.git)?$/';
      if (!isset($url['host']) || $url['host'] !== 'github.com' || !isset($url['path']) || !preg_match($regex, $url['path'])) {
        $form_state->setError($element, t('The repository must be a valid GitHub repository. The value must be in the format "https://github.com/username/repository" or "https://github.com/username/repository.git".'));
      }
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
      $return[$delta]['comment_num'] = $value['type'] === 'events' ? 0 : $value['comment_num'];
    }
    return $return;
  }

}
