<?php

declare(strict_types=1);

namespace Drupal\joinup_test_content\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cas\Service\CasUserManager;
use Drupal\cas_mock_server\UserManagerInterface;
use Drupal\default_content\Event\DefaultContentEvents;
use Drupal\default_content\Event\ImportEvent;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Acts on default_content events.
 */
final class DefaultContentSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'cas_mock_server.user_manager')]
    protected readonly UserManagerInterface $casMockUserManager,
    protected readonly CasUserManager $casUserManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Default content import event handler.
   */
  public function onDefaultContentImport(ImportEvent $event): void {
    if ($event->getModule() === 'joinup_test_content') {
      $this->ensureCasUserRegistration($event);
    }
    // Index imported items so they show up in Search API-driven listings
    // (e.g. configurable_listing paragraphs, sitewide views) without each
    // test having to trigger `drush search-api:index` itself.
    $this->indexAllEnabledIndexes();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DefaultContentEvents::IMPORT => ['onDefaultContentImport'],
    ];
  }

  /**
   * Ensures all users imported via default content are registered in CAS.
   *
   * Uses the username as password in CAS mock server.
   */
  private function ensureCasUserRegistration(ImportEvent $event): void {
    $entities = $event->getImportedEntities();
    foreach ($entities as $entity) {
      if (!$entity instanceof UserInterface) {
        continue;
      }

      $this->casMockUserManager->addUser([
        'username' => $entity->getAccountName(),
        'email' => $entity->getEmail(),
        'password' => $entity->getAccountName(),
      ]);
      $this->casUserManager->setCasUsernameForAccount($entity, $entity->getAccountName());
    }
  }

  /**
   * Indexes all items pending in every enabled Search API index.
   */
  private function indexAllEnabledIndexes(): void {
    $indexes = $this->entityTypeManager->getStorage('search_api_index')
      ->loadByProperties(['status' => TRUE]);
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index->indexItems();
    }
  }

}
