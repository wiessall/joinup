<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\MenuLinkContentStorageInterface;

/**
 * Provides implementation of "joinup_group.group_settings_form_alter" service.
 */
class GroupSettingsFormAlter implements GroupSettingsFormAlterInterface {

  use StringTranslationTrait;

  /**
   * Builds a new group settings form alter service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\joinup_group\GroupMenuManagerInterface $groupMenuManager
   *   Group menu manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GroupMenuManagerInterface $groupMenuManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $link = $this->getMenuLinkContent($form_state);

    if ($link) {
      $form['tab_overview'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Overview label'),
        '#description' => $this->t('It overwrites the default label for the "Overview" tab'),
        '#default_value' => $link->getTitle(),
        '#weight' => 10,
        '#required' => TRUE,
      ];

      $form['actions']['submit']['#submit'][] = [$this, 'submitCallback'];
    }
  }

  /**
   * Gets menu link content.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Menu link content.
   */
  protected function getMenuLinkContent(FormStateInterface $form_state): ?MenuLinkContentInterface {
    $group = $form_state->getFormObject()?->getEntity()?->getTargetEntity();
    if (!$group instanceof GroupInterface) {
      return NULL;
    }

    $menu = $this->groupMenuManager->getMenuByGroupId($group->id(), 'navigation');
    if (!$menu) {
      return NULL;
    }

    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
    assert($menuLinkContentStorage instanceof MenuLinkContentStorageInterface);

    $menuLinkQuery = $menuLinkContentStorage->getQuery();
    // Collect the IDs of links to the custom page.
    $ids = $menuLinkQuery->accessCheck(FALSE)
      ->condition('bundle', 'menu_link_content')
      ->condition('menu_name', "ogmenu-{$menu->id()}")
      ->condition($menuLinkQuery->orConditionGroup()
        ->condition('link.uri', "entity:{$group->toUrl()->getInternalPath()}")
        ->condition('link.uri', $group->toUrl()->toUriString())
      )
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return NULL;
    }

    $menuLinkContent = $menuLinkContentStorage->load(reset($ids));
    return $menuLinkContent instanceof MenuLinkContentInterface
      ? $menuLinkContent
      : NULL;
  }

  /**
   * Submission callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitCallback(array $form, FormStateInterface $form_state): void {
    $link = $this->getMenuLinkContent($form_state);
    $link?->set('title', $form_state->getValue('tab_overview'))
      ->save();
  }

}
