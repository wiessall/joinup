<?php

declare(strict_types=1);

namespace Drupal\topic\Hook;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;

/**
 * Hook implementations for the homepage topics queue form.
 */
final class HomepageTopicsQueueHooks {

  use DependencySerializationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_entity_subqueue_homepage_topics_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['#validate'][] = [$this, 'validate'];
    foreach (Element::children($form['actions']) as $action) {
      $form['actions'][$action]['#submit'][] = 'topic_form_entity_subqueue_homepage_topics_edit_form_alter_submit';
    }

    $help = [
      '#theme' => 'item_list',
      '#items' => [
        t('In this page you can add/remove/rearrange the topics that are displayed on the homepage.'),
        t('Add topics by name from the autocomplete field below.'),
        t('Only published topics can be added in the list.'),
        t('A maximum of 8 topics are allowed in the list.'),
      ],
    ];
    $form['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->renderer->renderInIsolation($help),
    ];
  }

  /**
   * Validates that a topic is not already in the homepage topics queue.
   */
  public function validate(array &$form, FormStateInterface $form_state): void {
    $items = $form_state->getValue('items');
    if (!isset($items['add_more']['new_item']['target_id'])) {
      return;
    }
    $existing_items = [];
    foreach ($items as $key => $item) {
      if (is_numeric($key) && isset($item['target_id'])) {
        $existing_items[] = $item['target_id'];
      }
    }
    if (in_array($items['add_more']['new_item']['target_id'], $existing_items)) {
      $entity = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($items['add_more']['new_item']['target_id']);
      $form_state->setErrorByName('items', t('The %topic topic is already in the list.', [
        '%topic' => $entity ? $entity->label() : $items['add_more']['new_item']['target_id'],
      ]));
    }
  }

}