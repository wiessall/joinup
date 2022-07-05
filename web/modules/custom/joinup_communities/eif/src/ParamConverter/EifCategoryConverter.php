<?php

declare(strict_types = 1);

namespace Drupal\eif\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides am {eif_category} route param converter.
 */
class EifCategoryConverter implements ParamConverterInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EifCategoryConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return isset($definition['type']) && $definition['type'] === 'eif_category';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $properties = [
      'vid' => 'eif_solution_type',
      'name' => ucfirst(str_replace('-', ' ', $value)),
    ];

    if ($terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties($properties)) {
      return reset($terms)->id();
    }
    return NULL;
  }

}
