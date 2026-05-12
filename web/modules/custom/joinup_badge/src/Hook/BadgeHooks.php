<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Entity\BadgeAwarding;
use Drupal\joinup_badge\Plugin\Field\FieldType\BadgeFieldItemList;
use Drupal\joinup_badge\PortalContributorBadgeEvaluator;
use Drupal\og\OgMembershipInterface;

/**
 * Hooks for the joinup_badge module.
 */
readonly class BadgeHooks {

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  protected const string IEC_UUID = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  /**
   * State key tracking the last 'portal_contributor' scan timestamp.
   */
  protected const string PORTAL_CONTRIBUTOR_LAST_SCAN_KEY = 'joinup_badge.portal_contributor.last_scan';

  /**
   * Minimum interval between two 'portal_contributor' scans, in seconds.
   */
  protected const int PORTAL_CONTRIBUTOR_SCAN_INTERVAL = 86400;

  public function __construct(
    protected BadgeService $badgeService,
    protected PortalContributorBadgeEvaluator $portalContributorEvaluator,
    protected StateInterface $state,
    protected TimeInterface $time,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'joinup_badge' => [
        'variables' => [
          'badge_type' => NULL,
          'label' => NULL,
          'description' => NULL,
          'icon' => NULL,
          'attributes' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    foreach ($this->badgeService->getBadgeFlags() as $flagId => $flag) {
      if (!empty($bundles['flagging'][$flagId])) {
        $bundles['flagging'][$flagId]['class'] = BadgeAwarding::class;
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entityType): array {
    if ($entityType->id() !== 'user') {
      return [];
    }

    $fields['badges'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Badges'))
      ->setDescription(new TranslatableMarkup('Badges awarded to this user.'))
      ->setSetting('target_type', 'flagging')
      ->setCardinality(3)
      ->setComputed(TRUE)
      ->setClass(BadgeFieldItemList::class)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', ['type' => 'joinup_badge'])
      ->setDisplayOptions('form', ['region' => 'hidden']);

    return $fields;
  }

  /**
   * Implements hook_og_membership_insert().
   */
  #[Hook('og_membership_insert')]
  #[Hook('og_membership_update')]
  public function awardInteroperableEuropeCommunityMembership(OgMembershipInterface $membership): void {
    if (
      $membership->getGroup()->uuid() === static::IEC_UUID
      // Only award the badge if the membership is approved.
      && $membership->getState() === OgMembershipInterface::STATE_ACTIVE
      && $membership->getOriginal()?->getState() !== OgMembershipInterface::STATE_ACTIVE
    ) {
      $this->badgeService->award('iec_member', $membership->getOwner());
    }
  }

  /**
   * Implements hook_cron().
   *
   * Once per day, finds the authors of any content (eligible nodes or
   * comments) published since the previous scan and queues each of them for
   * 'portal_contributor' badge evaluation.
   */
  #[Hook('cron')]
  public function scanRecentPortalContributors(): void {
    $now = $this->time->getCurrentTime();
    $lastScan = (int) $this->state->get(static::PORTAL_CONTRIBUTOR_LAST_SCAN_KEY, 0);
    if ($now - $lastScan < static::PORTAL_CONTRIBUTOR_SCAN_INTERVAL) {
      return;
    }
    $since = $lastScan > 0 ? $lastScan : $now - static::PORTAL_CONTRIBUTOR_SCAN_INTERVAL;
    $this->portalContributorEvaluator->enqueueRecentAuthors($since);
    $this->state->set(static::PORTAL_CONTRIBUTOR_LAST_SCAN_KEY, $now);
  }

}
