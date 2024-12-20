<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin for EU OSS Catalogue provider plugins.
 */
abstract class EuOssCatalogueProviderPluginBase extends PluginBase implements EuOssCatalogueProviderInterface, ContainerFactoryPluginInterface {
  /**
   * Static cache of the parser associated with this provider.
   */
  protected EuOssCatalogueParserInterface $parser;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
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
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
      $container->get('logger.channel.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterSolution(OssSolutionInterface $solution, Project $project): void {}

  /**
   * {@inheritdoc}
   */
  public function getParser(): EuOssCatalogueParserInterface {
    if (!isset($this->parser)) {
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
      $parser = $this->parserManager->createInstance($this->getPluginDefinition()['parser']);
      $this->parser = $parser;
    }
    return $this->parser;
  }

}
