<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\replicate_ui\Form\ReplicateConfirmForm;

/**
 * Overrides ReplicateConfirmForm with human-friendly text.
 */
class JoinupReplicateConfirmForm extends ReplicateConfirmForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $original = $this->getEntity();
    $originalLabel = $original->label();
    parent::submitForm($form, $form_state);

    if (!$original instanceof ContentEntityInterface) {
      return;
    }

    $clone = $form_state->get('replicated_entity');
    if (!$clone instanceof ContentEntityInterface) {
      // Probably an error.
      return;
    }

    $bundleSingularLabel = $this->entityTypeBundleInfo->getBundleInfo($original->getEntityTypeId())[$original->bundle()]['label_singular'] ?? NULL;
    $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
    $this->messenger()->addStatus($this->t(
      'The %title @bundle_label_singular @entity_type_label_singular has been cloned as %clone_title.',
      [
        '%title' => $originalLabel,
        '@bundle_label_singular' => $bundleSingularLabel,
        '@entity_type_label_singular' => $original->getEntityType()->getSingularLabel(),
        '%clone_title' => $clone->label(),
      ]
    ), TRUE);

    $form_state->setRedirectUrl($clone->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $entity = $this->getEntity();
    return $this->t('Set the title of the new @content-type-label cloned from %original-label', [
      '@content-type-label' => $entity->getEntityType()->getSingularLabel(),
      '%original-label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $entity = $this->getEntity();
    return $this->t('The new @content-type-label will be owned by you and created as unpublished.', [
      '@content-type-label' => $entity->getEntityType()->getSingularLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    $groupLabel = (string) $this->getEntity()->getEntityType()->getGroupLabel();
    return $this->t('Clone @content-type-group-label', [
      '@content-type-group-label' => mb_strtolower($groupLabel),
    ]);
  }

}
