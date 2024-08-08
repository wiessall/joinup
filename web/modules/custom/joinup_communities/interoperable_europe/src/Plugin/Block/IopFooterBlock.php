<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an IOP footer block.
 *
 * @Block(
 *   id = "interoperable_europe_iop_footer",
 *   admin_label = @Translation("IOP Footer"),
 *   category = @Translation("Interoperable Europe")
 * )
 */
class IopFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The theme extension list service.
   */
  protected ThemeExtensionList $themeExtensionList;

  /**
   * Constructs a new IopFooterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension list service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeExtensionList $theme_extension_list) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeExtensionList = $theme_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.theme'),
    );
  }

  /**
   * The plugin context handler.
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $icons = [
      'linkedin' => [
        'title' => $this->t('LinkedIn'),
        'url' => 'https://www.linkedin.com/groups/12907049/',
      ],
      'twitter' => [
        'title' => $this->t('Twitter'),
        'url' => 'https://twitter.com/InteroperableEU',
      ],
      'youtube' => [
        'title' => $this->t('Youtube'),
        'url' => 'https://www.youtube.com/c/interoperableeurope',
      ],
    ];

    foreach ($icons as $class => $data) {
      $build[$class] = [
        '#type' => 'inline_template',
        '#template' => '<a class="btn btn-sm rounded-circle join__btn join__btn--' . $class . '" href="{{ url }}" target="_blank" rel="noopener" title="{{ title }}"></a>',
        '#context' => [
          'icon' => $class,
          'title' => $this->t('Follow us on @media', ['@media' => $data['title']]),
          'url' => Url::fromUri($data['url']),
        ],
      ];
    }

    $build['newsletter'] = [
      '#type' => 'inline_template',
      '#template' => '<a class="btn btn-sm rounded-circle join__btn join__btn--newsletter" href="{{ url }}" target="_blank" rel="noopener" title="{{ title }}"></a>',
      '#context' => [
        'title' => $this->t('Subscribe to the interoperable europe newsletter'),
        'url' => Url::fromUri('https://ec.europa.eu/newsroom/informatics/user-subscriptions/1115/create'),
      ],
    ];
    return $build;
  }

}
