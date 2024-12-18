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

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
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
