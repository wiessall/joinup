<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\joinup_tmgmt\ExtractedDataMemo;
use Drupal\joinup_tmgmt\PathAliasCleaner;
use Drupal\joinup_tmgmt\Tmgmt\BlockFieldProcessor;
use Drupal\joinup_tmgmt\Tmgmt\IframeFieldProcessor;
use Drupal\joinup_tmgmt\Tmgmt\JoinupContentEntitySource;
use Drupal\joinup_tmgmt\Tmgmt\SocialMediaFieldProcessor;
use Drupal\tmgmt\JobItemInterface;

/**
 * Hook implementations for joinup_tmgmt.
 */
final class JoinupTmgmtHooks {

  public function __construct(
    protected readonly PathAliasCleaner $pathAliasCleaner,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ExtractedDataMemo $extractedDataMemo,
  ) {}

  /**
   * Implements hook_tmgmt_source_plugin_info_alter().
   */
  #[Hook('tmgmt_source_plugin_info_alter')]
  public function sourcePluginInfoAlter(array &$definitions): void {
    if (isset($definitions['content'])) {
      $definitions['content']['class'] = JoinupContentEntitySource::class;
    }
  }

  /**
   * Implements hook_entity_presave().
   *
   * Implements hook_entity_delete().
   */
  #[Hook('entity_presave')]
  #[Hook('entity_delete')]
  public function forgetExtractedData(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->extractedDataMemo->forget($entity);
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    if (isset($info['block_field'])) {
      $info['block_field']['tmgmt_field_processor'] = BlockFieldProcessor::class;
    }

    if (isset($info['iframe'])) {
      $info['iframe']['tmgmt_field_processor'] = IframeFieldProcessor::class;
    }

    if (isset($info['social_media_links_field'])) {
      $info['social_media_links_field']['tmgmt_field_processor'] = SocialMediaFieldProcessor::class;
    }
  }

  /**
   * Implements hook_tmgmt_content_presave_translation().
   *
   * Normalizes the URL alias the same way Pathauto cleans pattern tokens.
   *
   * @see \Drupal\pathauto\PathautoGenerator::createEntityAlias()
   * @see \Drupal\tmgmt_content\PathFieldProcessor::extractTranslatableData()
   */
  #[Hook('tmgmt_content_presave_translation')]
  public function tmgmtContentPresaveTranslation(ContentEntityInterface $translation, ContentEntityInterface $entity, array $data, JobItemInterface $job_item): void {
    if (!$translation->hasField('path') || empty($translation->path->alias)) {
      return;
    }

    // Only normalize the alias if TMGMT actually translated it.
    if (empty($data['path'][0]['alias']['#translate'])) {
      return;
    }

    $langcode = $translation->language()->getId();
    $alias = $translation->path->alias;

    // Skip if the alias is unchanged from what's already persisted.
    $path_alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $existing = $path_alias_storage->loadByProperties([
      'path' => '/' . $translation->toUrl()->getInternalPath(),
      'langcode' => $langcode,
    ]);
    $previous_alias = $existing ? reset($existing)->getAlias() : NULL;
    if ($previous_alias === $alias) {
      return;
    }

    $translation->path->alias = $this->pathAliasCleaner->cleanPath($alias, $langcode);
  }

}
