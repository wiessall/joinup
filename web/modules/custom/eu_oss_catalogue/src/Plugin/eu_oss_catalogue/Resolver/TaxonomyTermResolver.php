<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\Project;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Taxonomy term resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "taxonomy_term",
 *   label = "Taxonomy term",
 * )
 */
class TaxonomyTermResolver extends DefaultResolver implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $this->languageManger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'vid' => NULL,
      'controlled' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    parent::resolve($value, $project, $metadata, $fieldItemList);
    if (!$value) {
      return;
    }

    // Get a flat list of all term names regardless of the language.
    $allTermNames = array_values(array_merge(...array_values($value)));

    // Only supports SQL database storage for taxonomy terms.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($storage instanceof SqlContentEntityStorage);

    // Retrieve existing term IDs for the given term names.
    // Uses a BINARY mysql comparison to be case and accents sensitive.
    $records = $this->database->select($storage->getDataTable(), 't')
      ->fields('t', ['tid', 'name'])
      ->condition('vid', $this->getConfiguration()['vid'])
      ->where('BINARY [t].[name] IN (:names[])', [':names[]' => $allTermNames])
      ->execute()
      ->fetchAllAssoc('name');

    // Resolve each value item.
    $resolved = [];
    foreach ($value as $langcode => $names) {
      foreach ($names as $key => $name) {
        // If the term exists, use it.
        if (isset($records[$name])) {
          $resolved[$langcode][$key] = $records[$name]->tid;
          continue;
        }

        // Log exception for controlled vocabs when term doesn't exist.
        if ($this->getConfiguration()['controlled']) {
          $exception = new UnresolvableValueException($fieldItemList->getName(), $this->getConfiguration()['path'], $name, $metadata);
          Error::logException($this->logger, $exception, Error::DEFAULT_ERROR_MESSAGE, [], LogLevel::WARNING);
        }

        // Create new term.
        $resolved[$langcode][$key] = $storage->create([
          'vid' => $this->getConfiguration()['vid'],
          'name' => $name,
        ]);
      }
    }

    $value = $resolved;
  }

}
