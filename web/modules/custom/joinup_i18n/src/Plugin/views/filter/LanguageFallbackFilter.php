<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps each entity's current-language row, with default-translation fallback.
 *
 * Returns one row per entity. Pair with the "***LANGUAGE_entity_translation***"
 * rendering language so the row renders in that language.
 */
#[ViewsFilter('language_fallback')]
class LanguageFallbackFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $no_operator = TRUE;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('Content language, default fallback');
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Nothing to fall back to on a monolingual site.
    if (!$this->languageManager->isMultilingual()) {
      return;
    }

    $this->ensureMyTable();

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $id = $entity_type->getKey('id');
    $langcode = $entity_type->getKey('langcode');
    $default_langcode = $entity_type->getKey('default_langcode');
    $base_table = $this->view->storage->get('base_table');
    $alias = $this->tableAlias;

    // Keep the current-language row, or the default translation row when the
    // entity has no translation in the current language.
    $lang = '***LANGUAGE_' . LanguageInterface::TYPE_CONTENT . '***';
    $snippet = "($alias.$langcode = :lang OR ($alias.$default_langcode = 1 AND NOT EXISTS (SELECT 1 FROM {" . $base_table . "} translation WHERE translation.$id = $alias.$id AND translation.$langcode = :lang)))";
    $this->query->addWhereExpression($this->options['group'], $snippet, [':lang' => $lang]);
  }

}
