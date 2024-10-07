<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageBanInterface;
use Drupal\private_message\Form\ConfirmUnbanUserForm;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User Unblock confirmation form.
 */
class ConfirmUnblockUserForm extends ConfirmUnbanUserForm implements ContainerInjectionInterface {

  /**
   * Banned user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $bannedUser;

  /**
   * Selected ban.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageBanInterface
   */
  protected $ban;

  /**
   * Constructs a new ConfirmUnblockUserForm.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\private_message\Service\PrivateMessageBanManagerInterface $privateMessageBanManager
   *   The Private Message Ban manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service to retrieve parameters from the current route.
   */
  public function __construct(
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    PrivateMessageBanManagerInterface $privateMessageBanManager,
    RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($currentUser, $entityTypeManager, $privateMessageBanManager);
    $this->routeMatch = $routeMatch;
    $ban = $this->routeMatch->getParameter('private_message_ban');
    if (!empty($ban) && $ban instanceof PrivateMessageBanInterface) {
      $this->ban = $ban;
      $this->bannedUser = $ban->getTarget();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('private_message.ban_manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   returns form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL): array {

    return parent::buildForm($form, $form_state, $this->bannedUser->id());
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   returns question for modal.
   */
  public function getQuestion(): TranslatableMarkup {

    return $this->t('Are you sure you want to unblock user <em>%user</em>?', ['%user' => $this->bannedUser->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Url
   *   returns cancel url for modal.
   */
  public function getCancelUrl(): Url {
    return new Url('joinup_private_message.unblock', ['private_message_ban' => $this->ban->id()]);
  }

}
