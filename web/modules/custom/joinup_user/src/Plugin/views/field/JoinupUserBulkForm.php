<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\views\field;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_user\Entity\JoinupUserInterface;
use Drupal\joinup_user\JoinupUserNotificationHelperInterface;
use Drupal\role_delegation\Plugin\views\field\RoleDelegationUserBulkForm;
use Drupal\system\ActionConfigEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a user operations bulk form element.
 *
 * This overrides the default views bulk operations form from the User module to
 * send out a notification in case a moderator makes changes to a user account
 * through the bulk form.
 *
 * @see \Drupal\user\Plugin\views\field\UserBulkForm
 *
 * @ViewsField("joinup_user_bulk_form")
 */
class JoinupUserBulkForm extends RoleDelegationUserBulkForm {

  /**
   * A list of actions that require the affected user to be notified.
   */
  protected const ACTIONS_REQUIRING_NOTIFICATION = [
    'user_add_role_action.licence_manager',
    'user_add_role_action.moderator',
    'user_remove_role_action.licence_manager',
    'user_remove_role_action.moderator',
  ];

  /**
   * Constructs a new JoinupUserBulkForm Views field plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The currently logged in user.
   * @param \Drupal\joinup_user\JoinupUserNotificationHelperInterface $notificationHelper
   *   The notification helper for the Interoperable Europe Portal User module.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entityRepository,
    ResettableStackedRouteMatchInterface $routeMatch,
    AccountInterface $currentUser,
    protected JoinupUserNotificationHelperInterface $notificationHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager, $languageManager, $messenger, $entityRepository, $routeMatch, $currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('joinup_user.notification_helper')
    );
  }

  /**
   * Submit handler for the bulk form.
   *
   * This is overridden to allow us to send a notification to the user whose
   * account is affected. An override is necessary since the original form class
   * does not allow us to hook in to the bulk form action.
   *
   * The only change is an added call to `$this->notifyAffectedUsers()` right
   * after the actions have taken place.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user tried to access an action without access to it.
   *
   * @phpcs:disable Drupal.Commenting.FunctionComment.TypeHintMissing
   *   The 'array &$form' type hint is missing, but we cannot add it since this
   *   would make the method signature incompatible with the parent method.
   */
  public function viewsFormSubmit(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('step') == 'views_form_views_form') {
      // Filter only selected checkboxes. Use the actual user input rather than
      // the raw form values array, since the site data may change before the
      // bulk form is submitted, which can lead to data loss.
      $userInput = $form_state->getUserInput();
      $selected = array_filter($userInput[$this->options['id']]);
      $entities = [];
      $action = $this->actions[$form_state->getValue('action')];
      $count = 0;

      foreach ($selected as $bulkFormKey) {
        $entity = $this->loadEntityFromBulkFormKey($bulkFormKey);
        // Skip execution if current entity does not exist.
        if (empty($entity)) {
          continue;
        }
        // Skip execution if the user did not have access.
        if (!$action->getPlugin()->access($entity, $this->view->getUser())) {
          $this->messenger->addError($this->t('No access to execute %action on the @entity_type_label %entity_label.', [
            '%action' => $action->label(),
            '@entity_type_label' => $entity->getEntityType()->getLabel(),
            '%entity_label' => $entity->label(),
          ]));
          continue;
        }

        $count++;

        $entities[$bulkFormKey] = $entity;
      }

      // If there were entities selected but the action isn't allowed on any of
      // them, we don't need to do anything further.
      if (!$count) {
        return;
      }

      $action->execute($entities);

      $this->notifyAffectedUsers($action, $entities);

      $operationDefinition = $action->getPluginDefinition();
      if (!empty($operationDefinition['confirm_form_route_name'])) {
        $options = [
          'query' => $this->getDestinationArray(),
        ];
        $routeParameters = $this->routeMatch->getRawParameters()->all();
        $form_state->setRedirect($operationDefinition['confirm_form_route_name'], $routeParameters, $options);
      }
      else {
        // Don't display the message unless there are some elements affected and
        // there is no confirmation form.
        $this->messenger->addStatus($this->formatPlural($count, '%action was applied to @count item.', '%action was applied to @count items.', [
          '%action' => $action->label(),
        ]));
      }
    }
  }

  /**
   * Notifies the users whose accounts are affected by the given action.
   *
   * @param \Drupal\system\ActionConfigEntityInterface $action
   *   The action being taken.
   * @param \Drupal\joinup_user\Entity\JoinupUserInterface[] $users
   *   The user accounts affected by the action.
   */
  protected function notifyAffectedUsers(ActionConfigEntityInterface $action, array $users): void {
    // Only notify the user on selected actions.
    if (!in_array($action->id(), self::ACTIONS_REQUIRING_NOTIFICATION)) {
      return;
    }

    foreach ($users as $user) {
      // Sanity check. PHP doesn't have support for typed arrays yet.
      if (!$user instanceof JoinupUserInterface) {
        continue;
      }

      $this->notificationHelper->notifyOnAccountChange($user);
    }
  }

}
