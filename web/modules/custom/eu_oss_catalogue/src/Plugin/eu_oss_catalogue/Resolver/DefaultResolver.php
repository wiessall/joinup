<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default and fallback publiccode.yml value resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "default",
 *   label = "Default",
 * )
 */
class DefaultResolver extends PluginBase implements EuOssCatalogueResolverInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManger
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManger,
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
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'path' => NULL,
      'langcode_token' => '[lang]',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    $fieldDefinition = $fieldItemList->getFieldDefinition();
    $langcodeToken = $this->getConfiguration()['langcode_token'];

    $isTranslatable =
      str_contains($this->getConfiguration()['path'], $langcodeToken) &&
      $fieldDefinition->isTranslatable();

    $langcodes = $isTranslatable ? array_keys($this->languageManger->getLanguages()) : [LanguageInterface::LANGCODE_DEFAULT];
    $defaultLangcode = $this->languageManger->getDefaultLanguage()->getId();

    $value = [];
    foreach ($langcodes as $langcode) {
      $path = $this->getConfiguration()['path'];
      $path = $isTranslatable ? str_replace($langcodeToken, $langcode, $path) : $path;
      $trail = explode('/', $path);
      if (NestedArray::keyExists($metadata, $trail)) {
        // Replace a potential default langcode key with the pseudo-langcode
        // version. E.g. 'en' is replaced with 'x-default' in array keys.
        $langcode = $langcode === $defaultLangcode ? LanguageInterface::LANGCODE_DEFAULT : $langcode;
        $value[$langcode] = (array) NestedArray::getValue($metadata, $trail);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

}
