<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for EU OSS Catalogue parser plugins.
 */
abstract class EuOssCatalogueParserPluginBase extends PluginBase implements EuOssCatalogueParserInterface, ContainerFactoryPluginInterface {

  /**
   * Static cache for resolver plugin instances.
   *
   * @var \Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface[]
   */
  protected array $resolver;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $resolverManager
   *   The EU OSS Catalogue resolver plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU catalogue logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCataloguePluginManagerInterface $resolverManager,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManager,
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
      $container->get('plugin.manager.eu_oss_catalogue.resolver'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(Project $project): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  final public function parse(Project $project, EuOssCatalogueProviderInterface $provider): ?OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // For now, we're using the solution URL as UUID.
    $solutions = $nodeStorage->loadByProperties(['oss_git_url' => $project->url]);

    $solution = $solutions ? reset($solutions) : $nodeStorage->create(['type' => 'oss_solution']);
    \assert($solution instanceof OssSolutionInterface);

    try {
      // Populate project metadata array. This array is used by some parsers to
      // add metadata to the final stored node via resolvers.
      $metadata = $this->getMetadata($project);

      $latestReleaseDate = $project->latestReleaseDate ?
        new DrupalDateTime($project->latestReleaseDate) : NULL;

      $solution
        ->set('title', $this->getTitle($project, $metadata))
        ->set('oss_git_url', $project->url)
        ->set('oss_hosting_platform', $project->platform)
        ->set('oss_default_branch', $project->defaultBranch)
        ->set('oss_git_forks', $project->numberOfForks)
        ->set('oss_git_stars', $project->numberOfStars)
        ->set('oss_git_open_issues', $project->numberOfIssues)
        ->set('oss_software_version', $project->latestReleaseTag)
        ->set('oss_release_date', $latestReleaseDate?->format('Y-m-d'))
        ->set('oss_platform_reference', $project->platformReference)
        ->set('oss_git_description', [
          'value' => $project->description,
          'format' => 'markdown',
        ])
        ->set('oss_logo', $project->logo)
        ->set('oss_git_open_pull_requests', $project->numberOfPullRequests)
        ->addProvider($provider);

      $this->doParse($solution, $project, $metadata, $provider);
    }
    catch (\Throwable $exception) {
      Error::logException($this->logger, $exception);
      return NULL;
    }

    return $solution;
  }

  /**
   * Configures and returns a resolver given its plugin ID and configuration.
   *
   * @param string $resolverPluginId
   *   The resolver plugin ID.
   * @param array $configuration
   *   The resolver plugin configuration.
   */
  protected function getResolver(string $resolverPluginId, array $configuration): EuOssCatalogueResolverInterface {
    if (!isset($this->resolver[$resolverPluginId])) {
      $this->resolver[$resolverPluginId] = $this->resolverManager->createInstance($resolverPluginId);
    }
    $this->resolver[$resolverPluginId]->setConfiguration($configuration);
    return $this->resolver[$resolverPluginId];
  }

  /**
   * Returns the OSS solution title.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project's metadata.
   *
   * @return string
   *   The OSS solution title.
   */
  protected function getTitle(Project $project, array $metadata): string {
    return $project->getFullName();
  }

  /**
   * Returns the field EU OSS Catalogue third-party settings.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The field item list.
   *
   * @return array|null
   *   EU OSS Catalogue third-party settings or NULL if this field doesn't
   *   provide them. Structure:
   *     - override: (optional) The `override` setting is documented in
   *       \Drupal\eu_oss_catalogue\Model\FieldResolvingOverridePolicy. If
   *       omitted, defaults to 'always'.
   *     - resolvers: A list of EU OSS Catalogue resolver plugin options. Each
   *       option is an associative array containing the plugin ID and its
   *       configuration.
   */
  protected function getFieldSettings(FieldItemListInterface $fieldItemList): ?array {
    $fieldDefinition = $fieldItemList->getFieldDefinition();
    if ($fieldDefinition instanceof ThirdPartySettingsInterface) {
      if ($settings = $fieldDefinition->getThirdPartySetting('eu_oss_catalogue', $this->getPluginId())) {
        return $settings + ['override' => 'always'];
      }
    }
    return NULL;
  }

  /**
   * Checks that the resolved value has the expected structure.
   *
   * @param array|null $resolved
   *   The resolved value.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface $resolver
   *   The resolver plugin instance.
   *
   * @throws \Exception
   *   When the resolved value doesn't have a proper structure.
   */
  protected function checkResolved(?array $resolved, EuOssCatalogueResolverInterface $resolver): void {
    if ($resolved === NULL) {
      // NULL is allowed.
      return;
    }

    // Check $resolved array structure.
    $allowedLanguages = array_keys($this->languageManager->getLanguages());
    $defaultLanguage = $this->languageManager->getDefaultLanguage()->getId();
    // Replace default language with 'x-default'.
    $allowedLanguages = [
      ...array_diff($allowedLanguages, [$defaultLanguage]),
      ...[LanguageInterface::LANGCODE_DEFAULT],
    ];

    // Check that keys are all valid language codes.
    if ($invalidKeys = array_diff_key($resolved, array_flip($allowedLanguages))) {
      throw new \Exception('The $value array, returned by ' . $resolver::class . '::resolve()' . ', has invalid keys: ' . implode(', ', $invalidKeys));
    }

    // Check that each element value is an array.
    $validValues = array_filter($resolved, 'is_array');
    if ($invalidValues = array_keys(array_diff_key($resolved, $validValues))) {
      throw new \Exception('The $value["' . implode('"], $value["', $invalidValues) . '"] array element(s), returned by ' . $resolver::class . '::resolve()' . ', should be array(s)');
    }
  }

}
