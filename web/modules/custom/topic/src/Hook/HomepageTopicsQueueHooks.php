<?php

declare(strict_types=1);

namespace Drupal\topic\Hook;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for the homepage topics queue form.
 */
final class HomepageTopicsQueueHooks {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_entity_subqueue_homepage_topics_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['#validate'][] = [$this, 'validate'];
    foreach (Element::children($form['actions']) as $action) {
      $form['actions'][$action]['#submit'][] = [$this, 'formAlterSubmit'];
    }

    $help = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('In this page you can add/remove/rearrange the topics that are displayed on the homepage.'),
        $this->t('Add topics by name from the autocomplete field below.'),
        $this->t('Only published topics can be added in the list.'),
        $this->t('To edit a topic, go to <a href=":dashboard">Dashboard</a> > <a href=":topics">Topics</a> and click on the "Edit" button of the topic you want to update.', [
          ':dashboard' => Url::fromRoute('dashboard.page')->toString(),
          ':topics' => Url::fromRoute('view.topic_vocabulary.page')->toString(),
        ]),
        $this->t('Once you are done, come back here to add it to the list.'),
        $this->t('A maximum of 8 topics are allowed in the list.'),
      ],
    ];
    $form['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->renderer->renderInIsolation($help),
    ];
  }

  /**
   * Redirects to the same page after saving the form.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function formAlterSubmit(array &$form, FormStateInterface $form_state): void {
    // The default redirect of the form is to the admin page of the entity
    // subqueue. Moderators do not have access to the page so the redirect leads
    // to a 403. We redirect to the same page in order to also allow further
    // actions.
    $form_state->setRedirect($this->routeMatch->getRouteName(), $this->routeMatch->getRawParameters()->all());
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
      $form_state->setErrorByName('items', $this->t('The %topic topic is already in the list.', [
        '%topic' => $entity ? $entity->label() : $items['add_more']['new_item']['target_id'],
      ]));
    }
  }

}
