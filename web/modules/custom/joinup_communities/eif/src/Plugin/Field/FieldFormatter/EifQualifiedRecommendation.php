<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "eif_qualified_recommendation",
 *   label = @Translation("EIF Qualified Recommendation"),
 *   field_types = {
 *     "eif_qualified_recommendation",
 *   },
 * )
 */
class EifQualifiedRecommendation extends TextDefaultFormatter {

  public function __construct(
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    readonly FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    readonly string $view_mode,
    readonly array $third_party_settings,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach (parent::viewElements($items, $langcode) as $delta => $element) {
      /** @var \Drupal\eif\Entity\EifRecommendationInterface $term */
      if ($term = $storage->load($items[$delta]->getValue()['target_id'])) {
        $elements[] = [
          '#theme' => 'eif_qualified_recommendation',
          '#label' => $term->getFullLabel(),
          '#description' => $element,
          '#weight' => $term->getWeight(),
        ];
      }
    }

    // Rely on recommendation term weight to order the list.
    usort($elements, [SortArray::class, 'sortByWeightProperty']);

    return $elements;
  }

}
