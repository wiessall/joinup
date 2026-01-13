<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the assessment entity edit forms.
 */
class AssessmentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $messageArgs = ['%label' => $this->entity->toLink()->toString()];
    $loggerArgs = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New assessment %label has been created.', $messageArgs));
        $this->logger('assessments')->notice('New assessment %label has been created.', $loggerArgs);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The assessment %label has been updated.', $messageArgs));
        $this->logger('assessments')->notice('The assessment %label has been updated.', $loggerArgs);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl());

    return $result;
  }

}
