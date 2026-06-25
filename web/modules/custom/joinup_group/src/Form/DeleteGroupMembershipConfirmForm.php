<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a confirmation form for deleting group memberships.
 */
class DeleteGroupMembershipConfirmForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The group memberships.
   */
  protected array $memberships;

  /**
   * The membership's group entity.
   */
  protected GroupInterface $group;

  public function __construct(
    #[Autowire(service: 'og.access')]
    protected readonly OgAccessInterface $ogAccess,
    protected readonly PrivateTempStoreFactory $privateTempStoreFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
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
          '@type' => $this->getGroup()->get('type')->entity->getSingularLabel(),
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
            '@type' => $this->getGroup()->get('type')->entity->getSingularLabel(),
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
        '@type' => $this->getGroup()->get('type')->entity->getSingularLabel(),
      ]
    );

    $storage = $this->entityTypeManager->getStorage('og_membership');
    $storage->delete($memberships);
    $this->messenger()->addStatus($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.node.member_overview');
  }

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
        '@type' => $this->getGroup()->get('type')->entity->getSingularLabel(),
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
      $privateTempStore = $this->privateTempStoreFactory->get('joinup_group.og_membership_delete_action');
      if (!$this->memberships = $privateTempStore->get('memberships')) {
        throw new \RuntimeException('No memberships.');
      }
    }
    return $this->memberships;
  }

  /**
   * Returns the memberships group.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The memberships group entity.
   *
   * @throws \LogicException
   *   If the membership group is not a collection or a solution.
   */
  protected function getGroup(): GroupInterface {
    if (!isset($this->group)) {
      /** @var \Drupal\og\OgMembershipInterface[] $memberships */
      $memberships = $this->getMemberships();
      // Pick-up the first membership. They're all belonging to the same group.
      $membership = reset($memberships);

      $this->group = $membership->getGroup();
      if (!($this->group instanceof GroupInterface) || !in_array($this->group->bundle(), [
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
