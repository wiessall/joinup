<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\node\NodeInterface;

/**
 * Form to share content inside collections.
 */
class ShareForm extends ShareFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    return $this->doBuildForm($form, $form_state, $node);
  }

  /**
   * Gets the title for the form route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The entity being shared.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page/modal title.
   */
  public function getTitle(NodeInterface $node): TranslatableMarkup {
    return $this->buildTitle($node);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'share_content_form';
  }

  /**
   * Form builder for the share form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   The entity being shared.
   *
   * @return array
   *   The form structure.
   */
  public function doBuildForm(array $form, FormStateInterface $form_state, ?FieldableEntityInterface $entity = NULL): array {
    $this->entity = $entity;

    $form['share'] = [
      '#theme' => 'social_share',
      '#entity' => $this->entity,
    ];

    $groups = $this->getShareableGroups();
    $notification = $entity instanceof ShareableContentInterface
      ? $this->entity->getSharingNotification()
      : NULL;
    if ($groups && $notification) {
      $form['notification'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $notification,
      ];
    }
    foreach ($groups as $id => $group) {
      $bundle = $group->bundle();
      $form['groups'][$bundle][$id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['share-box__row'],
        ],
        'entity' => $this->entityTypeManager->getViewBuilder($group->getEntityTypeId())->view($group, 'compact'),
        'checkbox' => [
          '#type' => 'checkbox',
          '#title' => $group->label(),
          // Replicate the behaviour of the "checkboxes" element again.
          // @see \Drupal\Core\Render\Element\Checkboxes::processCheckboxes
          '#return_value' => $id,
          '#default_value' => FALSE,
          // Drop the extra "checkbox" key.
          '#parents' => ['groups', $id],
        ],
      ];
    }

    foreach (JoinupGroupHelper::GROUP_BUNDLES as $bundle) {
      if (!empty($form['groups'][$bundle])) {
        $form['groups'][$bundle] += [
          '#theme_wrappers' => ['fieldset'],
          '#title' => $this->bundleInfo->getBundleCountLabel('node', $bundle, count($groups), 'no_count_capitalize'),
          '#tree' => TRUE,
          '#access' => !empty($groups),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#extra_suggestion' => 'light_blue',
      '#value' => empty($groups) ? $this->t('Close') : $this->t('Share'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Keep only the checked entries.
    $groups = array_filter($form_state->getValue('groups'));
    $group_labels = [];
    // We can safely loop through these ids, as invalid options are handled
    // already by Drupal.
    foreach (array_keys($groups) as $id) {
      $groups[$id] = $this->entityTypeManager->getStorage('node')->load($id);
      assert($groups[$id] instanceof GroupInterface);
      $group_labels[] = $groups[$id]->label();
    }
    $this->shareOnGroups($groups);

    // Show a message if the content was shared on at least one group.
    if (!empty($groups)) {
      $this->messenger()->addStatus(
        $this->t('Item was shared on the following groups: @groups.', ['@groups' => implode(', ', $group_labels)])
      );
    }

    $form_state->setRedirectUrl($this->entity->toUrl());
  }

  /**
   * Gets the title for the form route.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being shared.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page/modal title.
   */
  public function buildTitle(EntityInterface $entity): TranslatableMarkup {
    if ($this->isModal() || $this->isAjaxForm()) {
      return $this->t('Share on');
    }
    else {
      return $this->t('Share %title on', ['%title' => $entity->label()]);
    }
  }

  /**
   * Retrieves a list of groups where the entity can be shared on.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   A list of groups where the current entity can be shared on.
   */
  protected function getShareableGroups(): array {
    // Being part also for the access check, do not allow the user to access
    // this page for entities without a field to store groups it is shared in.
    if (!$this->entity instanceof ShareableContentInterface) {
      return [];
    }

    $user_groups = $this->getUserGroupsByPermission($this->getPermissionForAction('share'));
    // Always show collections first.
    uasort($user_groups, function (GroupInterface $group1, GroupInterface $group2): int {
      return $group1->bundle() <=> $group2->bundle();
    });
    if ($parent = $this->getExcludedParent()) {
      unset($user_groups[$parent->id()]);
    }

    return array_diff_key($user_groups, array_flip($this->getAlreadySharedGroupIds()));
  }

  /**
   * Shares the current entity inside a group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface[] $groups
   *   The groups where the entity must be shared additionally.
   */
  protected function shareOnGroups(array $groups): void {
    assert($this->entity instanceof ShareableContentInterface);
    $this->entity->shareWith($groups);
  }

}
