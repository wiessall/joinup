<?php

namespace Drupal\import_ird_jsons\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders field_claims paragraphs attached to the current solution as a table.
 *
 * Grouped by collection (field_claimed_by). Uses Joinup's Bootstrap table
 * styling via #theme => 'table' + the 'table table-striped' CSS classes.
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

    $claims_field = $node->get('field_claims');
    if ($claims_field->isEmpty()) {
      return [];
    }

    // Group rows by collection label (field_claimed_by → collection node label).
    $groups = [];
    foreach ($claims_field->referencedEntities() as $paragraph) {
      $collection_label = $paragraph->get('field_claimed_by')->entity?->label()
        ?? $this->t('Unknown collection');

      $property_label = $paragraph->get('field_claim_property')->entity?->label() ?? '';

      $value = $this->resolveClaimValue($paragraph);

      $groups[(string) $collection_label][] = [
        $property_label,
        $value,
      ];
    }

    if (empty($groups)) {
      return [];
    }

    $build = [];
    foreach ($groups as $collection => $rows) {
      $build[] = [
        '#type'  => 'html_tag',
        '#tag'   => 'h4',
        '#value' => $collection,
      ];
      $build[] = [
        '#theme'      => 'table',
        '#header'     => [
          $this->t('Property'),
          $this->t('Value'),
        ],
        '#rows'       => $rows,
        '#attributes' => [
          // Bootstrap + Joinup table classes.
          'class' => ['table', 'table-striped', 'ird-annotation-table'],
        ],
        '#empty'      => $this->t('No claims available.'),
      ];
    }

    return $build;
  }

  private function resolveClaimValue($paragraph): string {
    // Prefer a referenced taxonomy term value over a literal string.
    $ref_field = $paragraph->get('field_claim_ref_value');
    if (!$ref_field->isEmpty()) {
      return $ref_field->entity?->label() ?? '';
    }

    $literal_field = $paragraph->get('field_claim_literal_value');
    if (!$literal_field->isEmpty()) {
      return (string) $literal_field->value;
    }

    return '';
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