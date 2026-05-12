<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueParser;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueParserPluginBase;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\FieldOverridingPolicy;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationResponseInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parser for publiccode.yml data.
 */
#[EuOssCatalogueParser(
  id: "publiccode_yml",
  label: new TranslatableMarkup("publiccode.yml data")
)]
class PublicCodeYml extends EuOssCatalogueParserPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCataloguePluginManagerInterface $resolverManager,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManager,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected PublicCodeValidatorInterface $publicCodeValidator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $this->entityTypeManager, $this->resolverManager, $logger, $this->languageManager);
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
      $container->get('config.typed'),
      $container->get('eu_oss_catalogue.publiccode_validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(Project $project): ValidationResponseInterface {
    return $this->publicCodeValidator->validate(
      $project->getPubliccodeYml()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getProcessedSolution(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $relevantLang = $this->getRelevantLangCode($project);
    if ($relevantLang !== $solution->language()->getId()) {
      if (!$solution->isNew()) {
        // Drupal forbids changing the entity langcode during an update. We are
        // completely recreating the node, including its `nid`, `vid`, `uuid`.
        // @see https://www.drupal.org/node/2443989
        $duplicate = $this->entityTypeManager->getStorage('node')->create($solution->toArray());
        $solution->delete();
        $solution = $duplicate;
      }

      $solution->set('langcode', $relevantLang)->set('default_langcode', TRUE);
    }

    foreach ($solution->getFields(FALSE) as $fieldName => $fieldItemList) {
      // It's a convention that all fields are prefixed with oss_*.
      if (!str_starts_with($fieldName, 'oss_')) {
        continue;
      }

      $settings = $this->getFieldSettings($fieldItemList);
      if ($settings === NULL) {
        // This field has no EU OSS Catalogue third-party settings.
        continue;
      }

      /** @var array[] $resolvers */
      $resolvers =& $settings['resolvers'];
      $override = FieldOverridingPolicy::from($settings['override']);

      if ($override === FieldOverridingPolicy::PreserveNotEmpty && !$fieldItemList->isEmpty()) {
        // Don't override a field not-empty field.
        continue;
      }

      // We don't allow resolvers to modify directly the original field item
      // list because this is passed only for read-only purposes. Resolvers
      // should use this object only to extract useful info about the field or
      // the host entity.
      $fieldItemListCopy = clone $fieldItemList;

      $resolved = NULL;
      foreach ($resolvers as ['plugin' => $pluginId, 'config' => $config]) {
        $resolver = $this->getResolver($pluginId, $config);
        try {
          $resolver->resolve($resolved, $project, $metadata, $fieldItemListCopy);
          $this->checkResolved($resolved, $resolver, $fieldItemListCopy);
        }
        catch (UnresolvableValueException $exception) {
          Error::logException($this->logger, $exception);
          // Fail softly, don't set this field.
          continue 2;
        }
      }

      if ($override === FieldOverridingPolicy::Always && empty($resolved)) {
        // Force-clear the field across all translations.
        foreach ($solution->getTranslationLanguages() as $langcode => $translation) {
          $solution->getTranslation($langcode)->set($fieldName, NULL);
        }
      }
      elseif ($override !== FieldOverridingPolicy::ResolvedNotEmpty || $resolved) {
        // Populate the node field.
        foreach ($resolved as $langcode => $values) {
          // Create a new translation on demand.
          if (!$solution->hasTranslation($langcode)) {
            $solution->addTranslation($langcode, $solution->toArray());
          }
          // An empty array unsets the field.
          $solution->getTranslation($langcode)->set($fieldName, $values ?: NULL);
        }
      }
    }

    return $solution;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMetadata(Project $project): array {
    return Yaml::decode($project->getPubliccodeYml()) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getTitle(Project $project, array $metadata): string {
    $title = $metadata['name'] ?? parent::getTitle($project, $metadata);
    // Eliminate multiple spaces.
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   *
   * @return string
   *   The language code of the richest entity translation.
   *
   * @todo Make this method protected in ISAICP-11133.
   */
  public function getRelevantLangCode(Project $project): string {
    $siteLangcodes = array_keys($this->languageManager->getLanguages());

    if (count($siteLangcodes) === 1) {
      return reset($siteLangcodes);
    }

    if (!$description = $this->getDescription($project)) {
      // Use site's default language if the publiccode.yml doesn't contain any.
      return $this->languageManager->getDefaultLanguage()->getId();
    }

    $langcodes = array_intersect(array_keys($description), $siteLangcodes);
    if (count($langcodes) === 1) {
      // Only one langcode left, no need to perform more checks.
      return reset($langcodes);
    }

    $featuresPerLang = array_reduce(
      $langcodes,
      function (array $counts, string $langcode) use ($description): array {
        $counts[$langcode] = count($description[$langcode]['features'] ?? []);
        return $counts;
      },
      [],
    );

    // The langcode exposing most features wins.
    return array_search(max($featuresPerLang), $featuresPerLang, TRUE);
  }

  /**
   * Returns the `description` section of the publiccode.yml file, if any.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   *
   * @return array<string, array{features?: string[], genericName?: string, longDescription?: string, shortDescription?: string, screenshots?: string[]}>
   *   The `description` section of the publiccode.yml file.
   */
  protected function getDescription(Project $project): array {
    if (!$project->getPubliccodeYml()) {
      return [];
    }

    try {
      $publicCode = Yaml::decode($project->getPubliccodeYml());
    }
    catch (InvalidDataTypeException) {
      return [];
    }

    if (!is_array($publicCode)) {
      return [];
    }

    return $publicCode['description'] ?? [];
  }

}
