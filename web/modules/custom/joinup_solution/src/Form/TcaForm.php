<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\TcaFormBase;
use Drupal\node\NodeInterface;

/**
 * A simple page that presents a TCA form for the solution creation.
 */
class TcaForm extends TcaFormBase {

  /**
   * The collection group from the route.
   *
   * @var \Drupal\collection\Entity\CollectionInterface
   */
  protected CollectionInterface $collection;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'solution_tca_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityBundle(): string {
    return 'solution';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTcaBlockId(): string {
    return 'simple_block:solution_tca';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    assert($node instanceof CollectionInterface);
    $this->collection = $node;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl($this->collection->toUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, ?NodeInterface $node = NULL): void {
    $form_state->setRedirect('joinup_solution.collection_solution.add', [
      'node' => $this->collection->id(),
    ]);
  }

}
