<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\joinup_flag\FlagType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a flag block.
 *
 * @Block(
 *   id = "joinup_flag_block",
 *   admin_label = @Translation("Flag block"),
 * )
 */
class FlagBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function defaultConfiguration(): array {
    return [
      'flag_type' => FlagType::Like,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['flag_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Flag type'),
      '#options' => FlagType::toAssociativeArray(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->configuration['flag_type'] = $values['flag_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $entity = $this->getEntityFromRoute();
    if (!$entity) {
      return [];
    }

    $flag = $this->getFlagForEntity($entity);
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
   * Get the flag for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return \Drupal\flag\FlagInterface|null
   *   Flag entity.
   */
  protected function getFlagForEntity(EntityInterface $entity): ?FlagInterface {
    $prefix = $this->configuration['flag_type'] . '_';

    $flags = array_filter($this->flagService->getAllFlags($entity->getEntityTypeID(), $entity->bundle()), function (FlagInterface $flag) use ($prefix) {
      return str_starts_with($flag->id(), $prefix);
    });

    return empty($flags) ? NULL : reset($flags);
  }

}
