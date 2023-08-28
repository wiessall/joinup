<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a condition with an optional node context.
 *
 * Mainly useful if we need a condition what returns TRUE on every single page
 * except on the canonical page of certain node types.
 *
 * @Condition(
 *   id = "joinup_optional_node_type",
 *   label = @Translation("Joinup Optional Node Type"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *   },
 *   provider = "node",
 * )
 */
class JoinupOptionalNodeType extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a JoinupOptionalNodeType instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Constructs the config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return array
   *   The form structure
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $nodeTypes = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();
    array_walk(
      $nodeTypes,
      function (NodeTypeInterface &$nodeType) {
        $nodeType = $nodeType->label();
      }
    );
    $form['bundles'] = [
      '#title' => $this->t('Node types'),
      '#type' => 'checkboxes',
      '#options' => $nodeTypes,
      '#default_value' => $this->configuration['bundles'],
      '#description' => $this->t("Node types to include / exclude. If negation isn't enabled, this condition plugin works quite the same as the built-in core condition plugin. But if negation is enabled, the condition will be TRUE even if node context isn't available.<br> This is what we want to use if we want to display a block on every single page except on node pages with the specified types."),
    ];
    return parent::buildConfigurationForm($form, $formState);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    $this->configuration['bundles'] = array_filter($formState->getValue('bundles'));
    parent::submitConfigurationForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    // Return true if no bundles are selected and negate option is disabled.
    if (empty($this->configuration['bundles']) && !$this->isNegated()) {
      return TRUE;
    }

    $node = $this->getContextValue('node');
    // ConditionManager takes care of negation config, but at this plugin we
    // want to have a final TRUE returned if node context is missing.
    return $node instanceof NodeInterface
      ? in_array($node->getType(), $this->configuration['bundles'], TRUE)
      : !$this->isNegated();
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (count($this->configuration['bundles']) > 1) {
      $bundles = $this->configuration['bundles'];
      $last = array_pop($bundles);
      $bundles = implode(', ', $bundles);

      if (empty($this->configuration['negate'])) {
        return $this->t('@bundle_type is @bundles or @last', [
          '@bundle_type' => $this->pluginDefinition['label'],
          '@bundles' => $bundles,
          '@last' => $last,
        ]);
      }
      else {
        return $this->t('Not on node page OR @bundle_type is not @bundles or @last', [
          '@bundle_type' => $this->pluginDefinition['label'],
          '@bundles' => $bundles,
          '@last' => $last,
        ]);
      }
    }
    $bundle = reset($this->configuration['bundles']);

    if (empty($this->configuration['negate'])) {
      return $this->t('@bundle_type is @bundle', [
        '@bundle_type' => $this->pluginDefinition['label'],
        '@bundle' => $bundle,
      ]);
    }
    else {
      return $this->t('Not on node page OR @bundle_type is not @bundle', [
        '@bundle_type' => $this->pluginDefinition['label'],
        '@bundle' => $bundle,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bundles' => [],
    ] + parent::defaultConfiguration();
  }

}
