<?php

declare(strict_types=1);

namespace Drupal\language_picker\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Language Selector block with Bootstrap popover.
 */
#[Block(
  id: 'language_picker',
  admin_label: new TranslatableMarkup('Language Picker'),
)]
class LanguageSelectorBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $current_language = $this->languageManager->getCurrentLanguage();
    $language_links = $this->buildLanguageLinks($current_language);

    // Build language grid content.
    $language_content = [
      '#theme' => 'language_picker_grid',
      '#languages' => $language_links,
    ];

    return [
      '#theme' => 'ventuno_popover',
      '#trigger_text' => strtoupper($current_language->getId()),
      '#content' => $language_content,
      '#popover_classes' => ['language-picker'],
      '#placement' => 'bottom',
      '#attached' => [
        'library' => ['language_picker/language_picker'],
      ],
    ];
  }

  /**
   * Builds the language links for the popover.
   *
   * @param \Drupal\Core\Language\LanguageInterface $current_language
   *   The current language.
   *
   * @return array
   *   Array of language link data sorted by weight.
   */
  protected function buildLanguageLinks(LanguageInterface $current_language): array {
    $languages = $this->languageManager->getLanguages();

    // Remove current language from dropdown list.
    unset($languages[$current_language->getId()]);

    $language_links = [];
    foreach ($languages as $language) {
      $language_links[] = [
        'code' => $language->getId(),
        'name' => $language->getName(),
        'url' => Url::fromRoute('<current>', [], ['language' => $language])
          ->toString(),
        'weight' => $language->getWeight(),
      ];
    }

    // Sort languages by weight.
    usort($language_links, fn($a, $b) => $a['weight'] <=> $b['weight']);

    return $language_links;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'languages:language_interface',
      'url.path',
      'url.query_args',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:language.negotiation',
    ]);
  }

}
