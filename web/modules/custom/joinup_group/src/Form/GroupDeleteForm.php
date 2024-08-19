<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;

/**
 * Prevents deletion of collections if there is an existing child solution.
 */
class GroupDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $entity = $this->getEntity();
    if (!$entity instanceof CollectionInterface || empty($entity->getSolutions())) {
      return $this->t('Are you sure you want to delete @type %name?', [
        '@type' => $entity->get('type')->entity->getSingularLabel(),
        '%name' => $entity->label(),
      ]);
    }

    return $this->t('The collection %collection cannot be deleted because it contains the following solutions:', [
      '%collection' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $entity = $this->getEntity();
    if (!($entity instanceof CollectionInterface) || empty($entity->getSolutions())) {
      return parent::getDescription();
    }

    return $this->t('You can delete your solutions or transfer them to another collection.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->getEntity();
    if (empty($entity) || $entity->isNew() || !($entity instanceof CollectionInterface)) {
      return $form;
    }

    $affiliates = $entity->getSolutions();
    if (empty($affiliates)) {
      return $form;
    }

    $list = array_map(
      fn (SolutionInterface $solution): Link => $solution->toLink($solution->label()),
      $affiliates
    );

    $form['solutions'] = [
      '#theme' => 'item_list',
      '#items' => $list,
      '#weight' => -1,
    ];
    unset($form['actions']['submit']);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This is copied from NodeDeleteForm because the class is marked as internal
   * and cannot be extended.
   */
  protected function getDeletionMessage(): TranslatableMarkup {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntity();

    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type = $node_type_storage->load($entity->bundle());

    if (!$entity->isDefaultTranslation()) {
      return $this->t('@language translation of the @type %label has been deleted.', [
        '@language' => $entity->language()->getName(),
        '@type' => $node_type->label(),
        '%label' => $entity->label(),
      ]);
    }

    return $this->t('The @type %title has been deleted.', [
      '@type' => $node_type->label(),
      '%title' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   *  This is copied from NodeDeleteForm because the class is marked as internal
   *  and cannot be extended.
   */
  protected function logDeletionMessage(): void {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntity();
    $this->logger('content')->info('@type: deleted %title.', [
      '@type' => $entity->getType(),
      '%title' => $entity->label(),
    ]);
  }

}
