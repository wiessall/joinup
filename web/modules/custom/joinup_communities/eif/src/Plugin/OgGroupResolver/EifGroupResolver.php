<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\OgGroupResolver;

use Drupal\eif\EifInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;
use Drupal\taxonomy\TermInterface;

/**
 * Resolves the group from the route.
 *
 * Use this to make the EIF Toolbox group available as a route context on the:
 * - Canonical page of the EIF recommendations taxonomy.
 * - Recommendations page.
 * - Solutions page.
 *
 * @OgGroupResolver(
 *   id = "eif_group",
 *   label = "Group entity for the EIF recommendations",
 *   description = @Translation("Sets the EIF Toolbox as a context for the EIF recommendations' canonical path.")
 * )
 */
class EifGroupResolver extends RouteGroupResolver {

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/rdf_entity/{rdf_entity}/eif-perspective' => 'rdf_entity',
      '/rdf_entity/{rdf_entity}/solutions/{arg_1}' => 'rdf_entity',
      '/taxonomy/term/{taxonomy_term}' => 'taxonomy_term',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if ($entity = $this->getContentEntity()) {
      if ($entity->id() === EifInterface::EIF_ID || ($entity instanceof TermInterface && $entity->bundle() === 'eif_recommendation')) {
        /** @var \Drupal\joinup_solution\Entity\SolutionInterface $solution */
        if ($solution = $this->entityTypeManager->getStorage('rdf_entity')->load(EifInterface::EIF_ID)) {
          $collection->addGroup($solution, ['route']);
          $this->stopPropagation();
        }
      }

      // The 'EIF perspective' page.
      if ($this->routeMatch->getRouteName() === 'entity.rdf_entity.eif_perspective' && $entity instanceof SolutionInterface) {
        $collection->addGroup($entity, ['route']);
        $this->stopPropagation();
      }
    }

    // The 'Solutions' custom page.
    if ($this->routeMatch->getRouteName() === 'eif.solutions') {
      /** @var \Drupal\custom_page\Entity\CustomPageInterface $custom_page */
      $custom_page = $this->routeMatch->getParameter('node');
      $solution = $custom_page->getGroup();
      $collection->addGroup($solution, ['route']);
      $this->stopPropagation();
    }
  }

}
