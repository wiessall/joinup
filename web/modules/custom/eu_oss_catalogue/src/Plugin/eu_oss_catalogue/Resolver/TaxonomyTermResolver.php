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

    // Get a flat list of all taxonomy term names regardless of the language.
    $allTermNames = array_values(array_merge(...array_values($value)));

    // Retrieve existing term IDs for the given term names.
    // We only support SQL database storage for taxonomy terms.
    // We use a BINARY mysql comparison to be case and accents sensitive.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($storage instanceof SqlContentEntityStorage);
    $query = $this->database->select($storage->getDataTable(), 't');
    $query->fields('t', ['tid', 'name']);
    $query->condition('vid', $this->getConfiguration()['vid']);
    $query->where('BINARY [t].[name] IN (:names[])', [':names[]' => $allTermNames]);
    $results = $query->execute()->fetchAllAssoc('name');

    $resolved = [];
    foreach ($value as $langcode => $names) {
      foreach ($names as $key => $name) {
        if (isset($results[$name])) {
          $resolved[$langcode][$key] = $results[$name]->tid;
        }
        else {
          // Log exception for controlled vocabs when term doesn't exist.
          if ($this->getConfiguration()['controlled']) {
            $exception = new UnresolvableValueException($fieldItemList->getName(), $this->getConfiguration()['path'], $name, $metadata);
            Error::logException($this->logger, $exception, Error::DEFAULT_ERROR_MESSAGE, [], LogLevel::WARNING);
          }

          $term = $storage->create([
            'vid' => $this->getConfiguration()['vid'],
            'name' => $name,
          ]);
          $resolved[$langcode][$key] = $term;
        }
      }
    }

    $value = $resolved;
  }

}
