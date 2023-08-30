<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\og\OgContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Publish your OSS block.
 *
 * @Block(
 *   id = "joinup_oss_guide",
 *   admin_label = @Translation("Publish your OSS"),
 *   category = @Translation("OSS Catalogue")
 * )
 */
class OssGuideBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new OssFooterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\OgContextInterface $ogContext
   *   The OG context provider.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected OgContextInterface $ogContext) {
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
      $container->get('og.context'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $icons = [
      'icon1' => [
        'title' => $this->t('Make sure you have a git repository'),
        'description' => $this->t('In order to publish your software to this catalogue, you need to include a YAML file generated with some information inside yourgit repository'),
      ],
      'icon2' => [
        'title' => $this->t('Fill the information form'),
        'description' => $this->t('First, you will have to fill some information like the supported languages, the name of your software, the release date,…'),
      ],
      'icon3' => [
        'title' => $this->t('Generate the YAML file'),
        'description' => $this->t('Once the form is filled, you will be able to generate the YAML with the button dedicated to it on the page'),
      ],
      'icon4' => [
        'title' => $this->t('Include your YAML file inside your git!'),
        'description' => $this->t('You must include this generated YAML file at the rooth of your git and the information will automaticly be retrieved in our platform. Your solution is now published!'),
      ],
    ];

    $build['header'] = [
      '#type' => 'inline_template',
      '#template' => '<h2>{{ title }}</h2><p>{{ subtitle }}</p>',
      '#context' => [
        'title' => $this->t('Do you want to publish your open souce solution?'),
        'subtitle' => $this->t('Just follow the guide!'),
      ],
    ];

    foreach ($icons as $class => $data) {
      $build[$class] = [
        '#type' => 'inline_template',
        '#template' => '<div>{{ class }} {{ title }} {{ description }}</div>',
        '#context' => [
          'icon' => $class,
          'title' => $data['title'],
          'description' => $data['description'],
        ],
      ];
    }

    return $build;
  }

}
