<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\github_feed\Plugin\Field\FieldType\GithubFeedItem;
use Symfony\Component\Validator\ConstraintViolationInterface;

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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['repository'] = [
      '#type' => 'url',
      '#title' => $this->t('Repository'),
      '#default_value' => $items[$delta]->repository ?? NULL,
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => ['' => $this->t('- Select a value -')] + GithubFeedItem::allowedTypeValues(),
      '#default_value' => $items[$delta]->type ?? NULL,
    ];

    $element['include_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include comments'),
      '#default_value' => $items[$delta]->include_comments ?? NULL,
    ];

    $element['item_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of items'),
      '#default_value' => $items[$delta]->item_num ?? NULL,
    ];

    $element['comment_num'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of comments'),
      '#default_value' => $items[$delta]->comment_num ?? NULL,
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return isset($violation->arrayPropertyPath[0]) ? $element[$violation->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if ($value['repository'] === '') {
        $values[$delta]['repository'] = NULL;
      }
      if ($value['type'] === '') {
        $values[$delta]['type'] = NULL;
      }
      if ($value['include_comments'] === '') {
        $values[$delta]['include_comments'] = NULL;
      }
      if ($value['item_num'] === '') {
        $values[$delta]['item_num'] = NULL;
      }
      if ($value['comment_num'] === '') {
        $values[$delta]['comment_num'] = NULL;
      }
    }
    return $values;
  }

}
