<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Hook;

use Drupal\assessments\AssessmentInterface;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Entity\BadgeAwarding;
use Drupal\joinup_badge\Entity\Certificate;
use Drupal\joinup_badge\Plugin\Field\FieldType\BadgeFieldItemList;
use Drupal\node\NodeInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;

/**
 * Hooks for the joinup_badge module.
 */
class BadgeHooks implements DestructableInterface {

  use StringTranslationTrait;

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  protected const string IEC_UUID = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  /**
   * Machine name of the Portal contributor badge flag.
   */
  protected const string PORTAL_CONTRIBUTOR_FLAG_ID = 'portal_contributor';

  /**
   * Minimum number of eligible contributions to qualify.
   */
  protected const int PORTAL_CONTRIBUTOR_THRESHOLD = 5;

  /**
   * Trailing window over which contributions are counted.
   */
  protected const string PORTAL_CONTRIBUTOR_PERIOD = '-2 months';

  /**
   * Entity types and bundles that count toward the badge.
   */
  protected const array PORTAL_CONTRIBUTOR_TARGETS = [
    'assessment' => ['report'],
    'comment' => ['comment', 'reply'],
    'node' => ['collection', 'solution', 'event', 'news', 'discussion', 'document'],
  ];

  /**
   * UIDs whose Portal contributor eligibility should be re-evaluated.
   *
   * Populated by presave hooks during the request, drained by destruct().
   *
   * @var array<int, int>
   */
  protected array $portalContributorAuthors = [];

  public function __construct(
    protected readonly BadgeService $badgeService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TimeInterface $time,
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

    if (!empty($bundles['node']['certificate'])) {
      $bundles['node']['certificate']['class'] = Certificate::class;
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
   * Implements hook_og_membership_update().
   */
  #[Hook('og_membership_update')]
  public function revokeBadgesForBlockedMembership(OgMembershipInterface $membership): void {
    if ($membership->getGroup()?->uuid() === static::IEC_UUID
      && $membership->getState() === OgMembershipInterface::STATE_BLOCKED) {
      $this->badgeService->revoke('iec_member', $membership->getOwner());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for user entities.
   */
  #[Hook('user_update')]
  public function trackCertificate(UserInterface $user): void {
    $this->updateUserCertificatesStatus($user);
    $userCertificates = $this->getUserCertificates($user, '1');
    if (!empty($userCertificates)) {
      $this->badgeService->award('certificate', $user);
    }
    else {
      $this->badgeService->revoke('certificate', $user);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for user entities.
   */
  #[Hook('user_delete')]
  public function deleteUserCertificates(UserInterface $user): void {
    $certificates = $this->getUserCertificates($user);
    $this->entityTypeManager->getStorage('node')->delete($certificates);
  }

  /**
   * Implements hook_og_membership_delete().
   */
  #[Hook('og_membership_delete')]
  public function revokeBadgesForDeletedMembership(OgMembershipInterface $membership): void {
    if ($membership->getGroup()?->uuid() === static::IEC_UUID) {
      $this->badgeService->revoke('iec_member', $membership->getOwner());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('node_presave')]
  #[Hook('comment_presave')]
  #[Hook('assessment_presave')]
  public function trackPortalContributor(NodeInterface|CommentInterface|AssessmentInterface $entity): void {
    $entityTypeId = $entity->getEntityTypeId();
    $bundles = static::PORTAL_CONTRIBUTOR_TARGETS[$entityTypeId] ?? [];
    if (!in_array($entity->bundle(), $bundles, TRUE)) {
      return;
    }

    // Comments have no publication date — first appearance equals publication.
    $field = $entity instanceof CommentInterface ? 'created' : 'published_at';
    if ($entity->get($field)->value !== $entity->getOriginal()?->get($field)->value) {
      $uid = (int) $entity->getOwnerId();
      $this->portalContributorAuthors[$uid] = $uid;
    }
  }

  /**
   * Implements hook_inline_entity_form_table_fields_alter().
   */
  #[Hook('inline_entity_form_table_fields_alter')]
  public function userCertificateIefTable(array &$fields, array $context): void {
    if (
      $context['parent_entity_type'] !== 'user' ||
      $context['field_name'] !== 'certificates' ||
      $context['entity_type'] !== 'node' ||
      $context['allowed_bundles'] !== ['certificate']
    ) {
      return;
    }

    // Add course field column.
    // @todo Consider removing the title.
    $fields['course'] = [
      'type' => 'field',
      'label' => $this->t('Course'),
      'weight' => 10,
      'display_options' => ['type' => 'entity_reference_label'],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Drains the set of authors marked during the request and awards the
   * Portal contributor badge to any that now meet the threshold.
   */
  public function destruct(): void {
    if (!$this->portalContributorAuthors) {
      return;
    }

    $authors = $this->portalContributorAuthors;
    $this->portalContributorAuthors = [];

    $cutoff = (new \DateTimeImmutable())
      ->setTimestamp($this->time->getRequestTime())
      ->modify(static::PORTAL_CONTRIBUTOR_PERIOD)
      ->getTimestamp();

    $userStorage = $this->entityTypeManager->getStorage('user');
    foreach ($authors as $uid) {
      if ($this->countPortalContributions($uid, $cutoff) < static::PORTAL_CONTRIBUTOR_THRESHOLD) {
        continue;
      }
      $user = $userStorage->load($uid);
      if ($user instanceof UserInterface) {
        $this->badgeService->award(static::PORTAL_CONTRIBUTOR_FLAG_ID, $user);
      }
    }
  }

  /**
   * Counts eligible contributions across all target entity types.
   *
   * Short-circuits as soon as the running total reaches the threshold.
   */
  protected function countPortalContributions(int $uid, int $cutoff): int {
    $total = 0;
    foreach (static::PORTAL_CONTRIBUTOR_TARGETS as $entityTypeId => $bundles) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $dateField = $entityTypeId === 'comment' ? 'created' : 'published_at';
      $total += (int) $this->entityTypeManager->getStorage($entityTypeId)
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($entityType->getKey('owner'), $uid)
        ->condition($entityType->getKey('published'), TRUE)
        ->condition($entityType->getKey('bundle'), $bundles, 'IN')
        ->condition($dateField, $cutoff, '>=')
        ->count()
        ->execute();
      if ($total >= static::PORTAL_CONTRIBUTOR_THRESHOLD) {
        return $total;
      }
    }
    return $total;
  }

  /**
   * Gets all user certificates.
   *
   * @param \Drupal\user\UserInterface $user
   *   Certificates owner.
   * @param string|null $status
   *   Status of certificates.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUserCertificates(UserInterface $user, ?string $status = NULL): array {
    $properties = [
      'type' => 'certificate',
      'uid' => $user->id(),
    ];

    if (!empty($status)) {
      $properties['status'] = $status;
    }

    return $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties($properties);
  }

  /**
   * Publish/unpublish user certificates.
   *
   * @param \Drupal\user\UserInterface $user
   *   Certificate owner.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateUserCertificatesStatus(UserInterface $user): void {
    $certificates = $this->getUserCertificates($user);
    foreach ($certificates as $certificate) {
      if ($user->isBlocked() && $certificate->isPublished()) {
        $certificate->setUnpublished();
      }
      elseif ($user->isActive() && !$certificate->isPublished()) {
        $certificate->setPublished();
      }
      $certificate->save();
    }
  }

}
