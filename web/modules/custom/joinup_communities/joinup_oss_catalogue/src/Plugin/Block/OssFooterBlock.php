<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OSS footer block.
 *
 * @Block(
 *   id = "joinup_oss_footer",
 *   admin_label = @Translation("OSS Footer"),
 *   category = @Translation("OSS Catalogue")
 * )
 */
class OssFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new OssFooterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ThemeExtensionList $themeExtensionList) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
   * {@inheritdoc}
   */
  public function build(): array {
    $icons = [
      'linkedin' => [
        'title' => $this->t('LinkedIn'),
        'url' => '',
      ],
      'twitter' => [
        'title' => $this->t('Twitter'),
        'url' => '',
      ],
    ];

    foreach ($icons as $class => $data) {
      $build[$class] = [
        '#type' => 'inline_template',
        '#template' => '<a class="btn btn-sm rounded-circle join__btn join__btn--' . $class . '" href="{{ url }}" target="_blank" rel="noopener" title="{{ title }}"></a>',
        '#context' => [
          'icon' => $class,
          'title' => $this->t('Follow us on @media', ['@media' => $data['title']]),
          'url' => empty($data['url']) ? $data['url'] : Url::fromUri($data['url']),
        ],
      ];
    }

    return $build;
  }

}
