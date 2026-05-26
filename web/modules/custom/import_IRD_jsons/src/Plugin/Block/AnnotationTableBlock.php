<?php

namespace Drupal\import_IRD_jsons\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders all annotation nodes linked to the current solution as a table.
 *
 * Place on the solution node display via /admin/structure/block.
 * One row per annotation field per collection — grouped by collection.
 *
 * @Block(
 *   id = "ird_annotation_table",
 *   admin_label = @Translation("IRD Annotation Table"),
 * )
 */
class AnnotationTableBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node || $node->bundle() !== 'solution') {
      return [];
    }

    $annotations = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['field_innovation' => $node->id()]);

    if (empty($annotations)) {
      return [];
    }

    // Fields that exist for structural reasons, not annotation content.
    $skip = ['field_innovation', 'og_audience'];

    $rows = [];
    foreach ($annotations as $annotation) {
      $collection = $annotation->get('og_audience')->entity?->label()
                 ?? $annotation->bundle();

      foreach ($annotation->getFields() as $name => $field) {
        if (!($field->getFieldDefinition() instanceof FieldConfigInterface)) {
          continue;
        }
        if (in_array($name, $skip, TRUE) || $field->isEmpty()) {
          continue;
        }
        $rows[] = [
          $collection,
          $field->getFieldDefinition()->getLabel(),
          $this->renderField($field),
        ];
      }
    }

    if (empty($rows)) {
      return [];
    }

    return [
      '#theme'      => 'table',
      '#header'     => ['Collection', 'Annotation', 'Value'],
      '#rows'       => $rows,
      '#attributes' => ['class' => ['ird-annotation-table']],
    ];
  }

  private function renderField($field): string {
    $type   = $field->getFieldDefinition()->getType();
    $values = [];
    foreach ($field as $item) {
      $values[] = match ($type) {
        'entity_reference' => $item->entity?->label() ?? '',
        'link'             => $item->uri ?? '',
        'boolean'          => $item->value ? 'Yes' : 'No',
        default            => (string) ($item->value ?? ''),
      };
    }
    return implode(', ', array_filter($values));
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  public function getCacheTags(): array {
    $node = $this->routeMatch->getParameter('node');
    return $node
      ? Cache::mergeTags(parent::getCacheTags(), $node->getCacheTags())
      : parent::getCacheTags();
  }

}