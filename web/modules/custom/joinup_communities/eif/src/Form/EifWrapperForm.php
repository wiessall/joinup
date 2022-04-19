<?php

declare(strict_types = 1);

namespace Drupal\eif\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the eif wrapper entity edit forms.
 */
class EifWrapperForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $vocabularies = [
      'eif_conceptual_model',
      'eif_interoperability_layer',
      'eif_principle',
      'eif_recommendation',
    ];

    $form['term'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Term'),
      '#default_value' => $this->getEntity()->get('term')->entity,
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => array_combine($vocabularies, $vocabularies),
      ],
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getEntity()->get('description')->value ?? NULL,
      '#format' => $this->getEntity()->get('description')['format'] ?? filter_default_format(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New eif wrapper %label has been created.', $message_arguments));
      $this->logger('eif')->notice('Created new eif wrapper %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The eif wrapper %label has been updated.', $message_arguments));
      $this->logger('eif')->notice('Updated new eif wrapper %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.eif_wrapper.collection');
  }

}
