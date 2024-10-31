<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessage;

/**
 * Provides a Private Message Unreport form.
 */
class PrivateMessageUnreportForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_unreport_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PrivateMessage $private_message = NULL): array {
    $form['private_message'] = [
      '#type' => 'value',
      '#value' => $private_message,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $privateMessage = $form['private_message']['#value'];
    $privateMessage->set('report', '');
    $privateMessage->set('report_timestamp', NULL);
    $privateMessage->save();
    $form_state->setRedirect('view.private_message_report.all');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Flag message as OK?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('view.private_message_report.all');
  }

}
