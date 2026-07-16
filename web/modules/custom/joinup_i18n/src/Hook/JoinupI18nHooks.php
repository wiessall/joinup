<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Hook;

use Drupal\babel_og_menu_link\BabelOgMenuLinkManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Hook implementations for joinup_i18n.
 */
final class JoinupI18nHooks {

  use StringTranslationTrait;

  /**
   * The Babel OG Menu Link manager.
   *
   * @var \Drupal\babel_og_menu_link\BabelOgMenuLinkManager|null
   */
  protected ?BabelOgMenuLinkManager $babelOgMenuLinkManager = NULL;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly ConfigInstallerInterface $configInstaller,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Injects the Babel OG Menu Link manager.
   *
   * @param \Drupal\babel_og_menu_link\BabelOgMenuLinkManager $babelOgMenuLinkManager
   *   The Babel OG Menu Link manager.
   */
  public function setBabelOgMenuLinkManager(BabelOgMenuLinkManager $babelOgMenuLinkManager): void {
    $this->babelOgMenuLinkManager = $babelOgMenuLinkManager;
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
        $continuousSettings['content'][$entityTypeId]['enabled'] = TRUE;
        $continuousSettings['content'][$entityTypeId]['bundles'][$bundle] = TRUE;
      }
    }

    return $continuousSettings;
  }

  /**
   * Implements hook_ENTITY_HOOK_update() for menu_link_content entity type.
   */
  #[Hook('menu_link_content_update')]
  public function updateMenuLinkContent(MenuLinkContentInterface $link): void {
    if (!$this->babelOgMenuLinkManager?->isLinkAllowed($link)) {
      return;
    }
    if ($link->isDefaultTranslation() || $link->language()->getId() !== 'en') {
      return;
    }
    $translationLangcodes = array_keys($link->getTranslationLanguages(FALSE));
    if (!$translationLangcodes) {
      return;
    }
    foreach ($translationLangcodes as $langcode) {
      $link->removeTranslation($langcode);
    }
    $link->save();
  }

  /**
   * Aborts a source's open job items when its source content changes.
   *
   * When the source gets a newer revision (a published default or a pending
   * forward-revision draft), its open job items, left in place:
   * - block the newer revision from being scheduled;
   * - apply stale content to the live version if accepted;
   * - stay stuck in review when a pending draft blocks acceptance.
   *
   * For continuous jobs, aborting the source's open job items whenever the
   * source content changes, before tmgmt_content_entity_update() re-schedules,
   * avoids all that.
   * TMGMT re-schedules for a published default; against a pending forward
   * revision ScheduleJobItemSubscriber declines, so the aborted item is only
   * replaced once the draft is published.
   *
   * @see tmgmt_content_entity_update
   * @see \Drupal\joinup_i18n\EventSubscriber\ScheduleJobItemSubscriber
   */
  #[Hook('entity_update', order: new OrderBefore(modules: ['tmgmt_content']))]
  public function abortOutdatedJobItems(EntityInterface $entity): void {
    if (
      !$entity instanceof ContentEntityInterface
      || !$entity->isTranslatable()
      || $entity->getEntityType()->get('entity_revision_parent_type_field')
      // Abort only when the source (default) translation changed. Saving a
      // target translation (e.g. TMGMT accepting one) also updates the entity
      // but does not outdate the other languages' open job items.
      || !$entity->getUntranslated()->hasTranslationChanges()
    ) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('tmgmt_job_item');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('plugin', 'content')
      ->condition('item_type', $entity->getEntityTypeId())
      ->condition('item_id', (string) $entity->id())
      ->condition('state', [
        JobItemInterface::STATE_INACTIVE,
        JobItemInterface::STATE_ACTIVE,
        JobItemInterface::STATE_REVIEW,
      ], 'IN')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $item) {
      \assert($item instanceof JobItemInterface);
      if ($item->getJob()?->isContinuous()) {
        $item->setState(JobItemInterface::STATE_ABORTED, 'Aborted because the source was updated.');
      }
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    if ($entity->getEntityTypeId() === 'tmgmt_job_item') {
      $operations['job_item_messages'] = [
        'title' => $this->t('Messages'),
        'url' => Url::fromRoute('view.joinup_job_item_messages.page', ['tmgmt_job_item' => $entity->id()]),
        'weight' => 50,
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      $data_table = $entity_type->isTranslatable() ? $entity_type->getDataTable() : NULL;
      if ($data_table && isset($data[$data_table])) {
        $data[$data_table]['language_fallback'] = [
          'title' => $this->t('Language fallback'),
          'help' => $this->t('Keeps the current-language row, falling back to the default translation.'),
          /* @see \Drupal\joinup_i18n\Plugin\views\filter\LanguageFallbackFilter */
          'filter' => ['id' => 'language_fallback'],
        ];
      }
    }
  }

}
