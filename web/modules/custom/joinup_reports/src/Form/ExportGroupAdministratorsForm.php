<?php

declare(strict_types=1);

namespace Drupal\joinup_reports\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_reports\ExportBatchedDataFormBase;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a form to export the list of group facilitators and administrators.
 */
class ExportGroupAdministratorsForm extends ExportBatchedDataFormBase {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cache,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($cache, $dateFormatter, $time);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectRouteName(): string {
    return 'joinup_reports.group_administrators_report';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDownloadedFileName(): string {
    return 'user_list.csv';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_export_group_administrators';
  }

  /**
   * Generated the user list data in a batch process.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function generateData(array &$form, FormStateInterface $form_state): void {
    $membership_query = $this->entityTypeManager->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE);
    $membership_or = $membership_query->orConditionGroup()
      ->condition('roles', 'node-collection-facilitator')
      ->condition('roles', 'node-solution-facilitator');
    $membership_ids = $this->entityTypeManager->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      // We are only exporting facilitators and administrators but all
      // administrators are also facilitators. Adding a condition that the
      // membership must have the facilitator role means that administrators are
      // also retrieved.
      ->condition($membership_or)
      ->condition('state', 'active')
      ->execute();

    $batch_operations = [];
    foreach ($membership_ids as $membership_id) {
      $batch_operations[] = [
        [$this, 'collectMembershipData'],
        [$membership_id],
      ];
    }

    if ($batch_operations) {
      $batch = [
        'title' => 'Exporting group administrators list',
        'init_message' => 'Starting collection of user data.',
        'operations' => $batch_operations,
        'finished' => [$this, 'storeToCache'],
      ];
      batch_set($batch);
    }
  }

  /**
   * Process a membership and collect necessary data.
   *
   * @param string $membership_id
   *   The membership id that will be processed during the batch operation.
   * @param array $context
   *   The batch context.
   */
  public function collectMembershipData(string $membership_id, array &$context): void {
    $membership = $this->entityTypeManager->getStorage('og_membership')->load($membership_id);
    if (!$membership instanceof OgMembershipInterface) {
      return;
    }

    $group = $membership->getGroup();
    $user = $membership->getOwner();
    if (!$group instanceof ContentEntityInterface || !$user instanceof UserInterface) {
      // The group and/or the user are not available which means that the
      // membership is orphaned and will be removed in the next cron run.
      return;
    }

    $user_url = $user->toUrl()->setAbsolute()->toString();
    $group_label = $group->label();
    $group_url = $group->toUrl()->setAbsolute()->toString();
    $is_administrator = (string) ($membership->hasRole("node-{$group->bundle()}-administrator") ? $this->t('Yes') : $this->t('No'));

    $context['results']['data'][$membership->id()] = [
      'User name' => $user->getDisplayName(),
      'User url' => $user_url,
      'User email' => $user->getEmail(),
      'Group bundle' => $group->bundle(),
      'Group ID' => $group->id(),
      'Group label' => $group_label,
      'Group url' => $group_url,
      'Is administrator' => $is_administrator,
    ];
  }

}
