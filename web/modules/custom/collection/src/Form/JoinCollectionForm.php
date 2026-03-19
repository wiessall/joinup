<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Form\JoinGroupFormBase;
use Drupal\og\OgMembershipInterface;

/**
 * A simple form with a button to join or leave a collection.
 */
class JoinCollectionForm extends JoinGroupFormBase {

  /**
   * {@inheritdoc}
   */
  public function getJoinSubmitLabel(): TranslatableMarkup {
    if (!$this->group->getSetting('label_join')->isEmpty()) {
      return new TranslatableMarkup(
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $this->group->getSetting('label_join')->getString(),
        [], ['context' => 'collection'],
      );
    }

    return parent::getJoinSubmitLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getLeaveSubmitLabel(): TranslatableMarkup {
    if (!$this->group->getSetting('label_leave')->isEmpty()) {
      return new TranslatableMarkup(
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $this->group->getSetting('label_leave')->getString(),
        [], ['context' => 'collection'],
      );
    }

    return parent::getLeaveSubmitLabel();
  }

  /**
   * Gets the wrapper format of the current request.
   *
   * @return string
   *   The wrapper format. Empty string if the wrapper format is not set.
   */
  protected function getRequestWrapperFormat(): string {
    return $this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, '');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->group->hasCodeOfConductToAccept()) {
      // Membership is created only after user accepted the code of conduct
      // document.
      return;
    }
    $state = $this->group->isClosed()
      ? OgMembershipInterface::STATE_PENDING
      : OgMembershipInterface::STATE_ACTIVE;
    $membership = $this->createSaveNewMembership($state, $this->user, $this->group);

    $this->messenger->addStatus($this->group->getNewMembershipSuccessMessage($membership));
    parent::submitForm($form, $form_state);
  }

}
