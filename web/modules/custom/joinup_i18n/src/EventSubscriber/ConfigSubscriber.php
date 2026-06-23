<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\joinup_i18n\Hook\JoinupI18nHooks;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to module configuration changes.
 */
final readonly class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected JoinupI18nHooks $joinupI18nHooks,
    protected Connection $db,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

  /**
   * Updates the continuous job settings when the configuration changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'joinup_i18n.settings' && $event->isChanged('tmgmt_content')) {
      $storage = $this->entityTypeManager->getStorage('tmgmt_job');
      // Use a direct query for the sake of performance.
      assert($storage instanceof SqlContentEntityStorage);
      $this->db->update('tmgmt_job')
        ->fields(['continuous_settings' => serialize($this->joinupI18nHooks->getContinuousSettings())])
        ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
        ->condition('state', JobInterface::STATE_CONTINUOUS)
        ->condition('translator', ETranslationTranslator::ID)
        ->condition('label', 'Joinup: %', 'LIKE')
        ->execute();
      $storage->resetCache();
    }
  }

}
