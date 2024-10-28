<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Defines argument validator plugin for Interoperable Europe Portal group ID.
 *
 * @ViewsArgumentValidator(
 *    id = "joinup_group_entity_id",
 *    title = @Translation("Interoperable Europe Portal group entity ID"),
 *    entity_type = "rdf_entity",
 * )
 */
class JoinupGroupEntityIdValidator extends Entity {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['rdf_entity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity ID'),
      '#default_value' => $this->options['rdf_entity_id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateEntity(EntityInterface $entity): bool {
    return ($entity->id() === $this->options['rdf_entity_id']) && parent::validateEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['rdf_entity_id'] = ['default' => FALSE];

    return $options;
  }

}
