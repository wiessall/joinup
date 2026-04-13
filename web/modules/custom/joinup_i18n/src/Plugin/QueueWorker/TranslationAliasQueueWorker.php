<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\QueueWorker;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\pathauto\PathautoGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates path aliases for entity translations.
 *
 * The default alias is replicated to all languages. For translated entities,
 * pathauto then generates a language-specific alias. If this differs, the
 * redirect module automatically creates a 301 redirect from the old alias.
 */
#[QueueWorker(
  id: 'joinup_i18n:translation_alias',
  title: new TranslatableMarkup('Generate translation path aliases'),
  cron: ['time' => 30],
)]
class TranslationAliasQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly AliasManagerInterface $aliasManager,
    protected readonly PathautoGeneratorInterface $pathautoGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('path_alias.manager'),
      $container->get('pathauto.generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity = $this->entityTypeManager
      ->getStorage($data['entity_type_id'])
      ->load($data['entity_id']);

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $source = '/' . $entity->toUrl()->getInternalPath();
    $default_langcode = $entity->language()->getId();
    $default_alias = $this->aliasManager->getAliasByPath($source, $default_langcode);
    if ($default_alias === $source) {
      return;
    }

    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    foreach ($this->languageManager->getLanguages() as $language) {
      $langcode = $language->getId();
      if ($langcode === $default_langcode) {
        continue;
      }

      if (!$alias_storage->loadByProperties(['path' => $source, 'alias' => $default_alias, 'langcode' => $langcode])) {
        $alias_storage->create(['path' => $source, 'alias' => $default_alias, 'langcode' => $langcode])->save();
      }
      if ($entity->hasTranslation($langcode)) {
        $this->pathautoGenerator->updateEntityAlias($entity->getTranslation($langcode), 'bulkupdate');
      }
    }
  }

}
