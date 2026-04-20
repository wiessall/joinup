<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\ContentLanguageSettingsInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Hook implementations for joinup_i18n.
 */
final readonly class JoinupI18nHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected Connection $db,
    protected ConfigInstallerInterface $configInstaller,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Update the job's continuous settings when a bundle is enabled or disabled.
   *
   * @param \Drupal\language\ContentLanguageSettingsInterface $settings
   *   The language content settings entity.
   */
  #[Hook('language_content_settings_insert')]
  #[Hook('language_content_settings_update')]
  #[Hook('language_content_settings_delete')]
  public function languageContentSettingsUpdate(ContentLanguageSettingsInterface $settings): void {
    if ($this->configInstaller->isSyncing()) {
      return;
    }

    // On delete, $wasEnabled equals FALSE because the original is NULL.
    $wasEnabled = !$settings->isNew() && $settings->getOriginal()?->getThirdPartySetting('content_translation', 'enabled', FALSE);
    $isEnabled = $settings->getThirdPartySetting('content_translation', 'enabled', FALSE);

    if ($isEnabled !== $wasEnabled) {
      $storage = $this->entityTypeManager->getStorage('tmgmt_job');
      // Use a direct query for the sake of performance.
      assert($storage instanceof SqlContentEntityStorage);
      $this->db->update('tmgmt_job')
        ->fields(['continuous_settings' => serialize($this->getContinuousSettings())])
        ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
        ->condition('state', JobInterface::STATE_CONTINUOUS)
        ->condition('translator', ETranslationTranslator::ID)
        ->condition('label', 'Joinup: %', 'LIKE')
        ->execute();
      $storage->resetCache();
    }
  }

  /**
   * Creates a continuous job when a new language is created.
   *
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language entity being inserted.
   */
  #[Hook('configurable_language_insert')]
  public function onLanguageInsert(ConfigurableLanguageInterface $language): void {
    if ($this->configInstaller->isSyncing() || $language->isLocked()) {
      return;
    }

    $newLangcode = $language->getId();
    $langcodes = array_keys($this->languageManager->getLanguages());
    unset($langcodes[array_search($newLangcode, $langcodes)]);

    $values = [
      'translator' => ETranslationTranslator::ID,
      'state' => JobInterface::STATE_CONTINUOUS,
      'job_type' => JobInterface::TYPE_CONTINUOUS,
      'continuous_settings' => $this->getContinuousSettings(),
    ];

    foreach ($langcodes as $langcode) {
      tmgmt_job_create($langcode, $newLangcode, 0, $values)
        ->set('label', "Joinup: $langcode > $newLangcode")
        ->save();
      tmgmt_job_create($newLangcode, $langcode, 0, $values)
        ->set('label', "Joinup: $newLangcode > $langcode")
        ->save();
    }
  }

  /**
   * Deletes the continuous jobs involving a deleted language.
   *
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language entity just deleted.
   */
  #[Hook('configurable_language_delete')]
  public function onLanguageDelete(ConfigurableLanguageInterface $language): void {
    $storage = $this->entityTypeManager->getStorage('tmgmt_job');

    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('translator', ETranslationTranslator::ID)
      ->condition('state', JobInterface::STATE_CONTINUOUS)
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS);
    $or = $query->orConditionGroup()
      ->condition('source_language', $language->getId())
      ->condition('target_language', $language->getId());
    $tjids = $query->condition($or)->execute();

    if ($tjids) {
      $storage->delete($storage->loadMultiple($tjids));
    }
  }

  /**
   * Returns the continuous job settings.
   *
   * @return array{content: array<string, array{enabled: true, bundles: array<string, true>}>}
   *   The continuous job settings array.
   */
  public function getContinuousSettings(): array {
    $continuousSettings = ['content' => []];
    $entityTypes = $this->configFactory->get('joinup_i18n.settings')->get('tmgmt_content');
    foreach ($entityTypes as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle) {
        // @todo Remove this exception in ISAICP-11093
        if ($entityTypeId === 'node' && $bundle === 'oss_solution') {
          continue;
        }

        $continuousSettings['content'][$entityTypeId]['enabled'] = TRUE;
        $continuousSettings['content'][$entityTypeId]['bundles'][$bundle] = TRUE;
      }
    }

    return $continuousSettings;
  }

}
