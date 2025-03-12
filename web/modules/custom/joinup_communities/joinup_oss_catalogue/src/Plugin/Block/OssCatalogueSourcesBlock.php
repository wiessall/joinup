<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_oss_catalogue\CatalogueSourceProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OSS catalogue sources block.
 */
#[Block(
  id: "joinup_oss_catalogue_sources",
  admin_label: new TranslatableMarkup("OSS catalogue sources"),
  category: new TranslatableMarkup("OSS Catalogue"),
)]
class OssCatalogueSourcesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly CatalogueSourceProcessor $processor,
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
      $container->get('Drupal\joinup_oss_catalogue\CatalogueSourceProcessor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('Catalogue'),
        $this->t('Type'),
        $this->t('Country'),
        $this->t('Description'),
        $this->t('Weblinks'),
      ],
      '#rows' => $this->processor->getAllProviders(),
      '#attributes' => ['class' => ['oss-catalogue-list']],
    ];
  }

}
