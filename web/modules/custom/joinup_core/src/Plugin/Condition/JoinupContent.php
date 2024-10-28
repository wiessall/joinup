<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a condition for joinup content.
 *
 * @Condition(
 *   id = "joinup_content",
 *   label = @Translation("Interoperable Europe Portal content"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"),
 *   required = FALSE),
 *     "rdf" = @ContextDefinition("entity:rdf_entity", label =
 *   @Translation("RDF entity"), required = FALSE),
 *   }
 * )
 */
class JoinupContent extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a new JoinupContent instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
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
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['content' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['content'] = [
      '#title' => $this->t('Interoperable Europe Portal content'),
      '#type' => 'checkboxes',
      '#options' => $this->getContentOptions(),
      '#default_value' => $this->configuration['content'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Gets content options.
   *
   * @return array
   *   Content options.
   */
  protected function getContentOptions(): array {
    $options = [];

    foreach (['node_type', 'rdf_type'] as $type) {
      $storage = $this->entityTypeManager->getStorage($type);

      foreach ($storage->loadMultiple() as $bundle) {
        $type = $this->getUniqueContentIdentifier($storage->getEntityType()
          ->get('bundle_of'), $bundle->id());

        $options[$type] = $bundle->label();
      }
    }

    return $options;
  }

  /**
   * Gets unique content identifier.
   *
   * @param string $type
   *   Entity type.
   * @param string $bundle
   *   Bundle type.
   *
   * @return string
   *   Unique content identifier.
   */
  protected function getUniqueContentIdentifier(string $type, string $bundle): string {
    return "$type-$bundle";
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['content'] = array_filter($form_state->getValue('content'));

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if ($this->configuration['content']) {
      return $this->t('Restricted');
    }

    return $this->t('Not Restricted');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getContextValue('rdf') ?? $this->getContextValue('node');
    if (!$entity) {
      return FALSE;
    }

    $type = $this->getUniqueContentIdentifier($entity->getEntityTypeId(), $entity->bundle());
    return str_ends_with($this->routeMatch->getRouteName(), '.canonical') && !empty($this->configuration['content'][$type]);
  }

}
