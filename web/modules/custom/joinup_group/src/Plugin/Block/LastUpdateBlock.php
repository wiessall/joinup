<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block showing the latest update date on collection and solution overviews.
 *
 * @Block(
 *   id = "joinup_group_last_update",
 *   admin_label = @Translation("Last update date"),
 *   category = @Translation("Group")
 * )
 */
class LastUpdateBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a CustomBlockExample object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date/time formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RouteMatchInterface $routeMatch, protected DateFormatterInterface $dateFormatter) {
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
      $container->get('current_route_match'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $output = [];

    if ($this->checkLatestUpdateDate()) {
      $output = [
        '#type' => 'item',
        '#title' => $this->t('Last update:'),
        '#markup' => $this->dateFormatter->format($this->checkLatestUpdateDate(), 'date_only'),
      ];
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cache_contexts = parent::getCacheContexts();
    return Cache::mergeContexts($cache_contexts, ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($this->checkLatestUpdateDate());
  }

  /**
   * Gets the timestamp of the last entity change.
   *
   * @return int|null
   *   The timestamp of the last entity save operation. Some entities allow a
   *   NULL value indicating the changed time is unknown.
   */
  protected function checkLatestUpdateDate(): ?int {
    $rdf_entity = $this->routeMatch->getParameter('rdf_entity');

    if ($rdf_entity instanceof GroupContentInterface && $changed_time = $rdf_entity->getChangedTime()) {
      return (int) $changed_time;
    }

    return NULL;
  }

}
