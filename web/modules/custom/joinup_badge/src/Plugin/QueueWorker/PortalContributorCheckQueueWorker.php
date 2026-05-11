<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\PortalContributorBadgeEvaluator;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Evaluates a single user for the 'portal_contributor' badge.
 */
#[QueueWorker(
  id: 'joinup_badge_portal_contributor_check',
  title: new TranslatableMarkup("Check users' eligibility for the Portal contributor badge"),
  cron: ['time' => 30],
)]
class PortalContributorCheckQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PortalContributorBadgeEvaluator $evaluator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get(PortalContributorBadgeEvaluator::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $uid = is_array($data) ? ($data['uid'] ?? NULL) : NULL;
    if (!$uid) {
      return;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user instanceof UserInterface) {
      $this->evaluator->evaluate($user);
    }
  }

}
