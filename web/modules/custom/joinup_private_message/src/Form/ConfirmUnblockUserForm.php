<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageBanInterface;
use Drupal\private_message\Form\ConfirmUnbanUserForm;
use Drupal\user\UserInterface;

/**
 * User Unblock confirmation form.
 */
class ConfirmUnblockUserForm extends ConfirmUnbanUserForm implements ContainerInjectionInterface {

  /**
   * Banned user.
   */
  protected UserInterface $bannedUser;

  /**
   * Selected ban.
   */
  protected PrivateMessageBanInterface $ban;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL, ?PrivateMessageBanInterface $private_message_ban = NULL): array {
    if (!empty($private_message_ban)) {
      $this->ban = $private_message_ban;
      $this->bannedUser = $private_message_ban->getTarget();
      return parent::buildForm($form, $form_state, $this->bannedUser->id());
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to unblock user <em>%user</em>?', ['%user' => $this->bannedUser->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('joinup_private_message.unblock', ['private_message_ban' => $this->ban->id()]);
  }

}
