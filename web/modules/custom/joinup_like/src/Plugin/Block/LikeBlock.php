<?php

declare(strict_types = 1);

namespace Drupal\joinup_like\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a like block.
 *
 * @Block(
 *   id = "joinup_like_block",
 *   admin_label = @Translation("Like block"),
 * )
 */
class LikeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a LikeBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match service.
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   Flag service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected RouteMatchInterface $routeMatch,
    protected FlagServiceInterface $flagService
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
      $container->get('current_route_match'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $entity = $this->getEntityFromRoute();
    if (!$entity) {
      return [];
    }

    $flag = $this->getLikeFlagForEntity($entity);
    if (!$flag || !$flag->status()) {
      return [];
    }

    $build = [];
    $build['#cache']['contexts'] = [
      'user.permissions',
      'url',
    ];
    $build['#cache']['tags'] = Cache::mergeTags(
      $flag->getCacheTags(), $entity->getCacheTags()
    );
    $build['flag_' . $flag->id()] = [
      '#lazy_builder' => [
        'flag.link_builder:build',
        [
          $entity->getEntityTypeId(),
          $entity->id(),
          $flag->id(),
        ],
      ],
      '#create_placeholder' => TRUE,
    ];

    return $build;
  }

  /**
   * Get Entity from route.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Entity.
   */
  protected function getEntityFromRoute(): ?EntityInterface {
    foreach ($this->routeMatch->getParameters() as $parameter) {
      if ($parameter instanceof EntityInterface) {
        return $parameter;
      }
    }

    return NULL;
  }

  /**
   * Get like flag for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return \Drupal\flag\FlagInterface|null
   *   Flag entity.
   */
  protected function getLikeFlagForEntity(EntityInterface $entity): ?FlagInterface {
    $flags = array_filter($this->flagService->getAllFlags($entity->getEntityTypeID(), $entity->bundle()), function (FlagInterface $flag) {
      return str_starts_with($flag->id(), 'like_');
    });

    return empty($flags) ? NULL : reset($flags);
  }

}
