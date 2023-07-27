<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
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
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManger
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LanguageManagerInterface $languageManger,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(EuOssCatalogueFetchedSolution $fetched, string $fieldName): mixed {
    if (!str_contains($this->configuration['path'], '[lang]')) {
      return parent::resolve($fetched, $fieldName);
    }

    $field = [];
    foreach ($this->languageManger->getLanguages() as $language) {
      $path = explode('/', str_replace('[lang]', $language->getId(), $this->configuration['path']));
      if (NestedArray::keyExists($fetched->data, $path)) {
        $data = $fetched->data;
        $field[$language->getId()] = [NestedArray::getValue($data, $path)];
      }
    }
    return $field;
  }

}
