<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Condition;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolution;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Oss content' condition.
 *
 * @Condition(
 *   id = "oss_content",
 *   label = @Translation("Oss content type"),
 *   context_definitions = {
 *      "node" = @ContextDefinition(
 *        "entity:node",
 *        label = @Translation("Node"),
 *        required = FALSE
 *      ),
 *      "rdf" = @ContextDefinition(
 *        "entity:rdf_entity",
 *        label = @Translation("RDF entity"),
 *        required = FALSE
 *      ),
 *    }
 * )
 */
class OssContentCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a new OssContentCondition instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    protected RouteMatchInterface $routeMatch
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('Oss content condition');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $node = $this->routeMatch->getParameter('node');
    $isOssContent = $node instanceof OssSolution;

    if ($this->isNegated() && $isOssContent) {
      return TRUE;
    }

    return $isOssContent;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

}
