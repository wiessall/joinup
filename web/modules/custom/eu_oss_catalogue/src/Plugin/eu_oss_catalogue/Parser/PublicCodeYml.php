<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
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
  protected function doParse(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): void {
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
          $this->checkResolved($resolved, $resolver);
        }
        catch (UnresolvableValueException $exception) {
          Error::logException($this->logger, $exception);
          // Fail softly, don't set this field.
          continue 2;
        }
      }

      if ($override === FieldOverridingPolicy::Always && empty($resolved) && empty($project->getSilentErrors())) {
        // Force-clear the field across all translations if there are no errors;
        // otherwise, publiccode.yml is not valid,
        // and we want to preserve the previous values.
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
  }

  /**
   * {@inheritdoc}
   */
  protected function getMetadata(Project $project): array {
    if ($project->getPubliccodeYml() === NULL) {
      $project->addSilentError("publiccodeYml does not exist for $project->url");
      return [];
    }
    try {
      return Yaml::decode($project->getPubliccodeYml()) ?? [];
    }
    catch (InvalidDataTypeException $exception) {
      $project->addSilentError("Cannot decode publiccode.yml of $project->url. Message: {$exception->getMessage()}");
      return [];
    }
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

}
