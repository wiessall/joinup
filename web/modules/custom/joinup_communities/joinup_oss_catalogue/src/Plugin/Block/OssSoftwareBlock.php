<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\joinup_oss_catalogue\Form\OssSoftwareName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OSS your software name block.
 *
 * @Block(
 *   id = "joinup_oss_software_name",
 *   admin_label = @Translation("OSS your software name"),
 *   category = @Translation("OSS Catalogue")
 * )
 */
class OssSoftwareBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new OssFooterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected FormBuilderInterface $formBuilder) {
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
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['subtitle'] = [
      '#type' => 'inline_template',
      '#template' => '<div>{{ text }}</div>',
      '#context' => [
        'text' => $this->t('Then continue with the form to fill…'),
      ],
    ];
    $build['form'] = $this->formBuilder->getForm(OssSoftwareName::class);
    return $build;
  }

}
