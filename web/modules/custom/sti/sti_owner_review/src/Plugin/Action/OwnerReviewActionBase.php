<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bulk actions of the owner review list: they act on owners and skip, with a count, those they do not apply to.
 */
abstract class OwnerReviewActionBase extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ProposalApplier $applier,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // PluginBase already has a messenger property (MessengerTrait); set it rather than redeclare it.
    $this->setMessenger($messenger);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('sti_owner_review.applier'), $container->get('messenger'));
  }

  /**
   * Acts on one owner.
   *
   * @return string|null
   *   NULL when done, else why it was skipped (shown as a count per reason).
   */
  abstract protected function apply(NodeInterface $owner): ?string;

  public function execute($entity = NULL): void {
    $this->executeMultiple([$entity]);
  }

  public function executeMultiple(array $entities): void {
    $done = 0;
    $skipped = [];
    foreach ($entities as $owner) {
      try {
        $reason = $this->apply($owner);
      }
      catch (\InvalidArgumentException | \RuntimeException $e) {
        $reason = $e->getMessage();
      }
      if ($reason === NULL) {
        $done++;
      }
      else {
        $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
      }
    }
    $this->messenger()->addStatus($this->t('@label: @n done.', ['@label' => $this->pluginDefinition['label'], '@n' => $done]));
    foreach ($skipped as $reason => $n) {
      $this->messenger()->addWarning($this->t('@n skipped: @reason', ['@n' => $n, '@reason' => $reason]));
    }
  }

  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($object instanceof NodeInterface && $object->bundle() === 'owner')
      ->andIf(AccessResult::allowedIfHasPermission($account, 'review owner proposals'));
    return $return_as_object ? $result : $result->isAllowed();
  }

}
