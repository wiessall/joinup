<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\taxonomy\TermInterface;
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
    protected LanguageManagerInterface $languageManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $this->languageManager);
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

    $resolvedValue = [];
    foreach ($value as $langcode => $names) {
      foreach ($names as $name) {
        $term = $this->resolveTerm($name, $metadata, $fieldItemList);
        $resolvedValue[$langcode][$name] = $term;
      }
    }

    $value = $resolvedValue;
  }

  /**
   * Resolve term by name.
   *
   * @param string $name
   *   The term name.
   * @param array $metadata
   *    The project metadata.
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *    The Drupal field item list instance.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The term object if found or created.
   */
  protected function resolveTerm(string $name, array $metadata, FieldItemListInterface $fieldItemList): TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Load or create term.
    $term = $storage->loadByProperties(['name' => $name, 'vid' => $this->getConfiguration()['vid']]);
    if (!empty($term)) {
      $term = reset($term);
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
    }

    return $term;
  }

}
