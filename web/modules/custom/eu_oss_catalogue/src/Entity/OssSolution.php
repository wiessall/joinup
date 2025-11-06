<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Plugin\Field\RelevantLanguageCode;
use Drupal\node\Entity\Node;
use Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceItem;

/**
 * Bundle class for 'oss_solution' nodes.
 */
class OssSolution extends Node implements OssSolutionInterface {

  use EntityAnonymousOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);
    $fields['uid'] = static::getAnonymousOwnerFieldDefinition($entity_type);
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    /** @var \Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceFieldItemListInterface $source */
    $source = $this->get('oss_source');
    foreach ($source->getValue() as $value) {
      if ($value['plugin_id'] === $provider->getPluginId()) {
        return $this;
      }
    }
    $source->appendItem($provider->getPluginId());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $this->get('oss_source')->filter(function (PluginReferenceItem $value) use ($provider): bool {
      return $value->plugin_id !== $provider->getPluginId();
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelevantLangCode(): string {
    return $this->get(RelevantLanguageCode::FIELD_NAME)->first()->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function isArchived(): bool {
    return $this->get('oss_archived')->getString() === '1';
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return $this->get('oss_git_url')->first()->getValue()['uri'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPubliccodeYaml(): ?string {
    $field = $this->get(static::PUBLICCODE_YAML_FIELD);
    \assert($field->getFieldDefinition()->isTranslatable() === FALSE);
    if ($field->isEmpty()) {
      return NULL;
    }
    $publicCodeYamlContent = $field->first()->getValue()[$field->first()->mainPropertyName()];
    return $publicCodeYamlContent;
  }

}
