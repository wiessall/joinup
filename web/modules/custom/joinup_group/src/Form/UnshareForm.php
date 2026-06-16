<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\node\NodeInterface;

/**
 * Form to unshare a community content from within groups.
 */
class UnshareForm extends ShareFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    return $this->doBuildForm($form, $form_state, $node);
  }

  /**
   * Access callback for the unshare form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    $this->entity = $node;

    $accessResult = AccessResult::allowedIf(
      $node instanceof ShareableContentInterface &&
      !empty($this->getGroups())
    );

    $accessResult->addCacheableDependency($node);

    if ($node instanceof ShareableContentInterface) {
      $sharingMeta = $node->getSharingMetaEntity();
      if (!$sharingMeta->isNew()) {
        $accessResult->addCacheableDependency($sharingMeta);
      }
    }
    return $accessResult;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(EntityInterface $node) {
    return $this->t('Unshare %title from', ['%title' => $node->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'unshare_content_form';
  }

  /**
   * Form builder for the unshare form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   The entity being unshared.
   *
   * @return array
   *   The form structure.
   */
  public function doBuildForm(array $form, FormStateInterface $form_state, ?FieldableEntityInterface $entity = NULL): array {
    $this->entity = $entity;

    $options = array_map(
      fn (GroupInterface $group) => $group->label(),
      $this->getGroups()
    );

    $form['groups'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Groups'),
      '#options' => $options,
      '#default_value' => [],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $groupIds = array_keys(array_filter($form_state->getValue('groups')));
    if (empty($groupIds)) {
      return;
    }
    $groups = $this->entityTypeManager->getStorage('node')->loadMultiple($groupIds);
    $groupLabels = array_map(
      fn (GroupInterface $group): TranslatableMarkup|string => $group->label(),
      $groups,
    );
    $this->removeFromGroups($groups);
    if (!empty($groupLabels)) {
      $this->messenger()->addStatus(
        $this->t('Item was unshared from the following groups: @groups.', ['@groups' => implode(', ', $groupLabels)])
      );
    }

    $form_state->setRedirectUrl($this->entity->toUrl());
  }

  /**
   * Gets a list of groups where the content can be unshared from the user.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   A list of groups where the user is a facilitator and the content is
   *   shared.
   */
  protected function getGroups(): array {
    $groups = $this->getAlreadySharedGroupIds();
    if (empty($groups)) {
      return $groups;
    }

    if ($this->currentUser->hasPermission('administer shared entities')) {
      return $this->entityTypeManager->getStorage('node')->loadMultiple($groups);
    }
    return array_intersect_key($this->getUserGroupsByPermission($this->getPermissionForAction('unshare')), array_flip($groups));
  }

  /**
   * Removes the current node from being shared inside a group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface[] $groups
   *   The groups to remove the node from.
   */
  protected function removeFromGroups(array $groups): void {
    assert($this->entity instanceof ShareableContentInterface);
    $this->entity->unShareFrom($groups);
  }

}
