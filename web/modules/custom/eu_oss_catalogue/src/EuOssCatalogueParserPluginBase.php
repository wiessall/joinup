<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
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
  final public function parse(Project $project, EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // For now, we're using the solution URL as UUID.
    $solutions = $nodeStorage->loadByProperties(['oss_git_url' => $project->url]);

    $solution = $solutions ? reset($solutions) : $nodeStorage->create(['type' => 'oss_solution']);
    \assert($solution instanceof OssSolutionInterface);

    // Populate the project metadata array. This array is used by some parsers
    // to add metadata to the final stored node via resolvers.
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

    $this->setLangcode($solution);

    return $solution;
  }

  /**
   * Parses project data into the OSS Solution node.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project's metadata.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   */
  abstract protected function doParse(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): void;

  /**
   * Sets the entity langcode to the relevant language, if needed.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS solution entity.
   */
  protected function setLangcode(OssSolutionInterface $solution): void {
    // Upstream code might have already set the relevant langcode before all
    // the resolvers got executed. Make sure we use a fresh field value.
    $relevantLang = $this->getRelevantLangCode($solution);
    $currentLang = $solution->language()->getId();

    if ($relevantLang === $currentLang) {
      return;
    }

    // Save the current default translation's field values.
    $currentDefaultValues = array_map(
      fn(FieldItemListInterface $fieldItemList) => $fieldItemList->getValue(),
      $solution->getTranslatableFields(FALSE),
    );

    if ($solution->hasTranslation($relevantLang)) {
      // Copy the relevant language translation's content to the default.
      $relevantTranslation = $solution->getTranslation($relevantLang);
      foreach ($solution->getTranslatableFields(FALSE) as $fieldName => $fieldItemList) {
        if ($fieldName !== 'langcode') {
          $solution->set($fieldName, $relevantTranslation->get($fieldName)->getValue());
        }
      }
      $solution->removeTranslation($relevantLang);
    }

    // Change the default translation's langcode to the relevant language.
    $solution->set('langcode', $relevantLang);

    // Restore the old default content as a translation for the original
    // language, unless that translation already exists (e.g., from a prior
    // TMGMT job).
    if (!$solution->hasTranslation($currentLang)) {
      $solution->addTranslation($currentLang, $currentDefaultValues);
    }
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
   * Returns project's metadata.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   *
   * @return array
   *   The project's metadata.
   */
  abstract protected function getMetadata(Project $project): array;

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
      throw new \Exception('The $value array, returned by ' . $resolver::class . '::resolve(), has invalid keys: ' . implode(', ', $invalidKeys));
    }

    // Check that each element value is an array.
    $validValues = array_filter($resolved, 'is_array');
    if ($invalidValues = array_keys(array_diff_key($resolved, $validValues))) {
      throw new \Exception('The $value["' . implode('"], $value["', $invalidValues) . '"] array element(s), returned by ' . $resolver::class . '::resolve(), should be array(s)');
    }
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @return string
   *   The language code of the richest entity translation.
   *
   * @todo Make this method protected in ISAICP-11133.
   */
  public function getRelevantLangCode(OssSolutionInterface $ossSolution): string {
    $translationLanguages = $ossSolution->getTranslationLanguages();
    if (count($translationLanguages) === 1) {
      return key($translationLanguages);
    }

    // If publiccode.yml field does not contain any language codes, then we
    // continue with the existing entity translations. But if we have
    // publiccode.yml language codes, then we use the union of the pc.yml
    // language codes and the entity translation language codes.
    $translationLanguageCodes = empty($publicCodeLanguageCodes = $this->getLanguageCodesFromPublicCodeYaml($ossSolution))
      ? array_keys($translationLanguages)
      : array_intersect($publicCodeLanguageCodes, array_keys($translationLanguages));

    // Only one translation left, no need to perform the "richest" check.
    if (count($translationLanguageCodes) === 1) {
      return reset($translationLanguageCodes);
    }

    $flagshipFieldItemCounts = array_reduce(
      $translationLanguageCodes ?: array_keys($translationLanguages),
      function (array $carry, string $languageCode) use ($ossSolution): array {
        $translation = $ossSolution->getTranslation($languageCode);
        $field = $translation->get('oss_features');
        $carry[$languageCode] = !$field->isEmpty() ? $field->count() : 0;
        return $carry;
      },
      [],
    );

    // We have 2 translation language keys at least, so we won't return FALSE.
    return array_search(max($flagshipFieldItemCounts), $flagshipFieldItemCounts, TRUE);
  }

  /**
   * Returns language codes used in publiccode.yml.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The OSS solution entity.
   *
   * @return string[]
   *   The language codes used at the description property. Returns empty array
   *   if:
   *   - The solution has no field data at the field.
   *   - The data is not a valid YAML string (so it cannot be decoded).
   *   - The decoded content is not an array.
   *   - The decoded content lacks the description key, or its value is not an
   *     array, or if it is empty.
   */
  protected function getLanguageCodesFromPublicCodeYaml(OssSolutionInterface $ossSolution): array {
    $publicCodeYamlContent = $ossSolution->getPublicCodeYaml();
    if (is_null($publicCodeYamlContent)) {
      return [];
    }

    try {
      $publicCodeYaml = Yaml::decode($publicCodeYamlContent);
    }
    catch (InvalidDataTypeException) {
      return [];
    }

    if (
      !is_array($publicCodeYaml)
      || empty($publicCodeYaml['description'])
      || !is_array($publicCodeYaml['description'])
    ) {
      return [];
    }

    return array_keys($publicCodeYaml['description']);
  }

}
