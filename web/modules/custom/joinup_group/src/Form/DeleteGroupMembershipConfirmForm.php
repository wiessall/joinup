<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting group memberships.
 */
class DeleteGroupMembershipConfirmForm extends ConfirmFormBase {

  /**
   * The user private tempstore.
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * The group memberships.
   */
  protected array $memberships;

  /**
   * The memberships group entity.
   */
  protected RdfInterface $group;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\og\OgAccessInterface $ogAccess
   *   The OG access service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The private tempstore factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   */
  public function __construct(
    protected OgAccessInterface $ogAccess,
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
  ) {
    $this->privateTempStore = $privateTempStoreFactory->get('joinup_group.og_membership_delete_action');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('og.access'),
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.group_membership'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Move the description at the bottom. It contains the standard warning.
    $form['description']['#weight'] = 100;

    $memberships = $this->getMemberships();
    if (count($memberships) === 1) {
      // Provide a singular specific message.
      $membership = reset($memberships);
      $form['message'] = [
        '#markup' => $this->t("The member %member will be deleted from the '%name' @type.", [
          '%member' => $membership->getOwner()->getDisplayName(),
          '%name' => $this->getGroup()->label(),
          '@type' => $this->getGroup()->get('rid')->entity->getSingularLabel(),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }
    else {
      // Provide a plural specific message. Cannot use PluralTranslatableMarkup
      // because we need to output a render array.
      $form['message'] = [
        [
          '#theme' => 'item_list',
          '#title' => $this->t('The following members:'),
          '#items' => array_map(
            function (OgMembershipInterface $membership): string {
              return $membership->getOwner()->getDisplayName();
            },
            $memberships
          ),
        ],
        [
          '#markup' => $this->t("will be deleted from the '%name' @type.", [
            '%name' => $this->getGroup()->label(),
            '@type' => $this->getGroup()->get('rid')->entity->getSingularLabel(),
          ]),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $memberships = $this->getMemberships();
    $names = implode(', ', array_map(
      function (OgMembershipInterface $membership): string {
        return $membership->getOwner()->getDisplayName();
      },
      $memberships
    ));

    $message = new PluralTranslatableMarkup(
      count($memberships),
      "The member %member has been deleted from the '%name' @type.",
      "The following members were removed from the '%name' @type: %member",
      [
        '%member' => $names,
        '%name' => $this->getGroup()->label(),
        '@type' => $this->getGroup()->get('rid')->entity->getSingularLabel(),
      ]
    );

    $storage = $this->entityTypeManager->getStorage('og_membership');
    $storage->delete($memberships);
    $this->messenger()->addStatus($message);
    foreach ($memberships as $member) {
      $this->logger->info('The member @actor has been deleted from the @group.', [
        '@actor' => $member->getOwner()->getDisplayName(),
        '@group' => $this->getGroup()->label(),
        'type' => 'joinup:deleted_membership',
        'actor' => $member->getOwner()->id(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {}

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return new PluralTranslatableMarkup(
      count($this->getMemberships()),
      "Are you sure you want to delete the selected membership from the '%name' @type?",
      "Are you sure you want to delete the selected memberships from the '%name' @type?",
      [
        '%name' => $this->getGroup()->label(),
        '@type' => $this->getGroup()->get('rid')->entity->getSingularLabel(),
      ]
    );
  }

  /**
   * Provides an access check callback for the form route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('Anonymous');
    }

    try {
      $group = $this->getGroup();
    }
    catch (\RuntimeException $exception) {
      if ($exception->getMessage() === 'No memberships.') {
        return AccessResult::forbidden('No memberships');
      }
      // Propagate any other \RuntimeException.
      throw $exception;
    }

    return $this->ogAccess->userAccess($group, 'manage members', $account);
  }

  /**
   * Returns the list of memberships to be removed.
   *
   * @return \Drupal\og\OgMembershipInterface[]
   *   A list of OG membership entities.
   *
   * @throws \RuntimeException
   *   When something wrong happened while trying to delete memberships.
   */
  protected function getMemberships(): array {
    if (!isset($this->memberships)) {
      if (!$this->memberships = $this->privateTempStore->get('memberships')) {
        throw new \RuntimeException('No memberships.');
      }
    }
    return $this->memberships;
  }

  /**
   * Returns the memberships group.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   The memberships group entity.
   *
   * @throws \LogicException
   *   If the membership group is not a collection or a solution.
   */
  protected function getGroup(): RdfInterface {
    if (!isset($this->group)) {
      /** @var \Drupal\og\OgMembershipInterface[] $memberships */
      $memberships = $this->getMemberships();
      // Pick-up the first membership. They're all belonging to the same group.
      $membership = reset($memberships);

      /** @var \Drupal\rdf_entity\RdfInterface $group */
      $this->group = $membership->getGroup();
      if ($this->group->getEntityTypeId() !== 'rdf_entity' || !in_array($this->group->bundle(), [
        'collection',
        'solution',
      ])) {
        throw new \LogicException("The group can only be a collections or a solutions.");
      }
    }

    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_og_membership_delete_action_confirm_form';
  }

}
