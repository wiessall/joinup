<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationIssue;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationResponseInterface;
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
  public function getPublicCodeYaml(): ?string {
    $field = $this->get(static::PUBLICCODE_YAML_FIELD);
    \assert($field->getFieldDefinition()->isTranslatable() === FALSE);
    return $field->isEmpty()
      ? NULL
      : $field->first()->getValue()[$field->first()->mainPropertyName()];
  }

  /**
   * {@inheritdoc}
   */
  public function setValidationResult(ValidationResponseInterface $validationResult): self {
    $status = $validationResult->isValid() ? ValidationStatus::Valid : ValidationStatus::Invalid;

    $issues = array_map(
      fn(ValidationIssue $issue): array => [
        'key' => $issue->key,
        'description' => $issue->description,
        'line' => $issue->line,
        'column' => $issue->column,
        'type' => $issue->type->value,
      ],
      $validationResult->getIssues(),
    );

    return $this->set(static::PUBLICCODE_VALIDATION_STATUS_FIELD, $status->value)
      ->set(static::PUBLICCODE_VALIDATION_ISSUES_FIELD, $issues);
  }

}
