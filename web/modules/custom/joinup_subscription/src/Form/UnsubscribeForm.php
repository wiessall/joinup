<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_subscription\JoinupSubscriptionsHelper;
use Drupal\joinup_subscription\Plugin\Field\FieldType\EntityBundlePairItem;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form that allows the user to unsubscribe from all groups.
 */
class UnsubscribeForm extends ConfirmFormBase {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The entity type bundle info service.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The OG membership manager service.
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * The group, if any.
   */
  protected ?GroupInterface $group;

  /**
   * Types of content to unsubscribe from, if any.
   */
  protected ?array $types;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Renderer $renderer, EntityTypeBundleInfoInterface $bundle_info, MembershipManagerInterface $membership_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->bundleInfo = $bundle_info;
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info'),
      $container->get('og.membership_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'unsubscribe_from_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): MarkupInterface {
    if (!$this->group) {
      return $this->t('Unsubscribe from all?');
    }
    return $this->t('Unsubscribe from %group @type?', [
      '%group' => $this->group->label(),
      '@type' => $this->group->get('rid')->entity->getSingularLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): MarkupInterface {
    if (!$this->group) {
      return $this->t('Are you sure you want to unsubscribe from all collections and/or solutions? You will stop receiving news and updates, including the pending memberships, from the following:');
    }
    $arguments = [
      '%group' => $this->group->label(),
      '@type' => $this->group->get('rid')->entity->getSingularLabel(),
    ];
    if (!$this->types) {
      return $this->t('Are you sure you want to unsubscribe from %group @type? You will stop receiving news and updates, including the pending memberships, from this @type.', $arguments);
    }
    return $this->t('Are you sure you want to unsubscribe from the %group @type content types? You will stop receiving updates for the following types of content from this @type:', $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $user = $this->getRouteMatch()->getParameter('user');
    if (!$user instanceof UserInterface) {
      throw new \InvalidArgumentException("The passed route parameter ({$this->getRouteMatch()->getRawParameter('user')}) is not a valid user ID.");
    }
    if ($user->id() == $this->currentUser()->id()) {
      return Url::fromRoute('joinup_subscription.my_subscriptions');
    }
    return Url::fromRoute('joinup_subscription.subscriptions', [
      'user' => $user->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL, ?GroupInterface $group = NULL, ?array $types = NULL): array {
    $this->group = $group;
    $this->types = $types;
    $form_state->set('#user', $user);

    $form = parent::buildForm($form, $form_state);
    if (!$group && $memberships_ids = $this->getUserMembershipIds($user)) {
      $memberships = $this->entityTypeManager->getStorage('og_membership')->loadMultiple($memberships_ids);
      $labels = [];
      foreach ($memberships as $membership) {
        if ($group = $membership->getGroup()) {
          $labels[$group->bundle()][] = $group->label();
        }
      }
      $form += $this->getGroupBuild($labels);
    }
    elseif ($group && $types) {
      $form['items'] = [
        '#theme' => 'item_list',
        '#items' => $this->getTypesLabels(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->get('#user');

    $redirect_url = $this->getCancelUrl();
    $form_state->setRedirect($redirect_url->getRouteName(), $redirect_url->getRouteParameters());

    if ($this->group) {
      $membership = $this->membershipManager->getMembership($this->group, $user->id());
      $types = $this->types ?: JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES[$this->group->bundle()] + [
        'announcements' => 'announcements',
      ];
      static::membershipUnsubscribeTypes($membership, $types);
      $arguments = [
        '%group' => $this->group->label(),
        '@type' => $this->group->get('rid')->entity->getSingularLabel(),
        '@types' => implode(', ', $this->getTypesLabels()),
      ];
      if (!$this->types) {
        $message = $this->t('You will no longer receive notifications from %group @type.', $arguments);
      }
      else {
        $message = $this->t('You will no longer receive notifications from %group @type, for: @types.', $arguments);
      }
      $this->messenger()->addStatus($message);
      return;
    }

    // On multiple memberships we use batch processing.
    $membership_ids = $this->getUserMembershipIds($user);
    $operations = [];
    foreach ($membership_ids as $membership_id) {
      $operations[] = [
        static::class . '::membershipUnsubscribe',
        [$membership_id],
      ];
    }

    $batch = [
      'title' => $this->t('Unsubscribe from all'),
      'operations' => $operations,
      'finished' => [$this, 'membershipUnsubscribeFinish'],
      'init_message' => $this->t('Initiating...'),
      'progress_message' => $this->t('Processed @current out of @total.'),
    ];

    batch_set($batch);
  }

  /**
   * Unsubscribes from a group.
   *
   * @param int $membership_id
   *   The membership ID.
   * @param array $context
   *   The batch context array.
   */
  public static function membershipUnsubscribe(int $membership_id, array &$context = []): void {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = \Drupal::entityTypeManager()->getStorage('og_membership')->load($membership_id);
    if ($group = $membership->getGroup()) {
      static::membershipUnsubscribeTypes($membership);
      $context['results']['labels'][$group->bundle()][] = $group->label();
      $context['results']['user'] = $membership->getOwner();
    }
  }

  /**
   * Unsubscribes from a group using a granular method.
   *
   * @param \Drupal\og\OgMembershipInterface $membership
   *   The membership entity.
   * @param array|null $types
   *   Types of content to unsubscribe from. Associative array keyed by entity
   *   type ID and having a list of bundles as value. A special case is the
   *   announcements key.
   */
  protected static function membershipUnsubscribeTypes(OgMembershipInterface $membership, ?array $types = NULL): void {
    /** @var \Drupal\joinup_subscription\Plugin\Field\FieldType\EntityBundlePairItem $field_item */
    $membership->get('subscription_bundles')->filter(function (EntityBundlePairItem $field_item) use ($types): bool {
      if ($types === NULL) {
        return FALSE;
      }
      return !isset($types[$field_item->getEntityTypeId()]) || !in_array($field_item->getBundleId(), $types[$field_item->getEntityTypeId()]);
    });
    if ($types === NULL || isset($types['announcements'])) {
      $membership->set('announcements', FALSE);
    }
    $membership->save();
  }

  /**
   * Implements callback_batch_finished.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function membershipUnsubscribeFinish(bool $success, array $results, array $operations): RedirectResponse {
    // @see \callback_batch_finished()
    if ($success) {
      $list = $this->getGroupBuild($results['labels']);
      $arguments = [
        '@items' => $this->renderer->render($list),
      ];
      $message = $this->t('You will no longer receive notifications for the following:<br />@items', $arguments);
      $this->messenger()->addStatus($message);
    }
    else {
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $message = $this->t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $this->messenger()->addError($message);
    }

    if ($results['user']->id() == $this->currentUser()->id()) {
      $url = Url::fromRoute('joinup_subscription.my_subscriptions');
    }
    else {
      $url = Url::fromRoute('joinup_subscription.subscriptions', [
        'user' => $results['user']->id(),
      ]);
    }

    return new RedirectResponse($url->setAbsolute()->toString());
  }

  /**
   * Provides an access check callback for the UnsubscribeForm routes.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserInterface $user
   *   The user being unsubscribed.
   * @param \Drupal\joinup_group\Entity\GroupInterface|null $group
   *   The group, if passed in route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(AccountInterface $current_user, UserInterface $user, ?GroupInterface $group = NULL): AccessResultInterface {
    // Deny access if the user is not logged in.
    if ($current_user->isAnonymous()) {
      return AccessResult::forbidden();
    }

    if ($group) {
      $membership = $this->membershipManager->getMembership($group, $user->id());
      $access_result = AccessResult::allowedIf((bool) $membership)->addCacheableDependency($membership);
    }
    else {
      $cache_tags = $this->entityTypeManager->getDefinition('og_membership')->getListCacheTags();
      $has_subscriptions = (bool) $this->getUserMembershipIds($user);
      $access_result = AccessResult::allowedIf($has_subscriptions)->addCacheTags($cache_tags);
    }
    // If the user has no membership, deny the access.
    if (!$access_result->isAllowed()) {
      return $access_result;
    }

    // User admins are allowed.
    $access_result->andIf(AccessResult::allowedIfHasPermission($current_user, 'administer users'));
    if ($access_result->isAllowed()) {
      return $access_result;
    }

    // Users can unsubscribe from their subscriptions.
    return $access_result->orIf(AccessResult::allowedIf($user->id() == $current_user->id()));
  }

  /**
   * Provides a title callback for the UnsubscribeForm routes.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being unsubscribed.
   * @param \Drupal\joinup_group\Entity\GroupInterface|null $group
   *   The group, if passed in route.
   * @param array|null $types
   *   Types of content to unsubscribe from, if passed im route.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The translated page title.
   */
  public function title(UserInterface $user, ?GroupInterface $group = NULL, ?array $types = NULL): MarkupInterface {
    if (!$group) {
      return $this->t('Unsubscribe from all groups');
    }

    $arguments = [
      '@group' => $group->label(),
      '@type' => $group->get('rid')->entity->getSingularLabel(),
    ];

    if (!$types) {
      return $this->t('Unsubscribe from @group @type', $arguments);
    }

    $this->types = $types;
    $arguments += ['@types' => implode(', ', $this->getTypesLabels())];

    return $this->t('Unsubscribe from @group @type, content types: @types', $arguments);
  }

  /**
   * Returns an array of membership IDs that the user has active subscriptions.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being unsubscribed.
   *
   * @return int[]
   *   An array of membership IDs.
   */
  protected function getUserMembershipIds(UserInterface $user): array {
    $condition_group = $this->entityTypeManager->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      ->orConditionGroup();

    $condition_group
      ->exists('subscription_bundles')
      ->condition('announcements', 1);

    return $this->entityTypeManager
      ->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $user->id())
      ->condition($condition_group)
      ->execute();
  }

  /**
   * Builds an item list of group labels.
   *
   * @param array $labels
   *   Group labels, grouped by group type.
   *
   * @return array
   *   A item list render array.
   */
  protected function getGroupBuild(array $labels): array {
    array_walk($labels, function (array &$group_labels): void {
      asort($group_labels);
    });

    $build = [];
    foreach ($labels as $bundle => $group_labels) {
      $build[$bundle] = [
        '#theme' => 'item_list',
        '#title' => $this->bundleInfo->getBundleCountLabel('rdf_entity', $bundle, count($group_labels), 'no_count_capitalize'),
        '#items' => $group_labels,
      ];
    }

    return $build;
  }

  /**
   * Returns a human-readable list of types of content to be unsubscribed.
   *
   * @return array
   *   A list of content type plural labels.
   */
  protected function getTypesLabels(): array {
    $items = [];
    foreach ((array) $this->types as $entity_type_id => $bundles) {
      if ($entity_type_id === 'announcements') {
        $items[] = $this->t('announcements');
        continue;
      }
      foreach ($bundles as $bundle) {
        $items[] = $this->bundleInfo->getBundleInfo($entity_type_id)[$bundle]['label_plural'];
      }
    }
    return $items;
  }

}
