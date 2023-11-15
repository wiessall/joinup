<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the `[lang]` token in path.
 *
 * This token is mostly used by publiccode.yml parser.
 *
 * @EuOssCatalogueResolver(
 *   id = "langcode",
 *   label = "Language code",
 * )
 */
class LangCodeResolver extends DefaultResolver implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
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
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
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
  public function resolve(Project $project, array $metadata, FieldItemListInterface $fieldItemList): array {
    if (!str_contains($this->getConfiguration()['path'], '[lang]')) {
      return parent::resolve($project, $metadata, $fieldItemList);
    }
    $fieldName = $fieldItemList->getName();

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution */
    $solution = $fieldItemList->getEntity();
    $values = [];
    foreach ($this->languageManger->getLanguages() as $langcode => $language) {
      $path = explode('/', str_replace('[lang]', $langcode, $this->getConfiguration()['path']));
      if (NestedArray::keyExists($metadata, $path)) {
        $value = NestedArray::getValue($metadata, $path);
        if (!$solution->hasTranslation($langcode)) {
          $solution->addTranslation($langcode, $solution->toArray());
        }
        $solution->getTranslation($langcode)->set($fieldName, $value);
        $values[$langcode] = $value;
      }
    }

    return $values;
  }

}
