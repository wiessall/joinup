<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to get flagged entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("joinup_flag_flagged_entity")
 */
class JoinupFlagFlaggedEntity extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['view_mode'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Display Mode'),
      '#default_value' => $this->options['view_mode'],
      '#description' => $this->t('Select the display mode for entities.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|array {
    $flagging = $this->getEntity($values);
    if (!$flagging instanceof FlaggingInterface) {
      return '';
    }
    $flaggable = $flagging->getFlaggable();
    $viewMode = $this->options['view_mode'] ?: 'default';
    return $this->entityTypeManager
      ->getViewBuilder($flaggable->getEntityTypeId())
      ->view($flaggable, $viewMode);
  }

}
