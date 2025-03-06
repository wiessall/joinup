<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\joinup_flag\FlagType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a flag block.
 */
#[Block(
  id: "joinup_flag_block",
  admin_label: new TranslatableMarkup("Flag block"),
)]
class FlagBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly FlagServiceInterface $flagService,
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
      '#default_value' => $this->configuration['flag_type'],
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
          'default',
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
    $allFlags = $this->flagService->getAllFlags($entity->getEntityTypeID(), $entity->bundle());
    return $allFlags[$this->configuration['flag_type']] ?? NULL;
  }

}
