<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\Form\NodeDeleteForm as OriginalForm;

/**
 * Prevents deletion of collections if there is an existing child solution.
 */
class GroupDeleteForm extends OriginalForm {

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

}
