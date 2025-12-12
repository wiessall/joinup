<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Filters by taxonomy country code.
 */
#[ViewsFilter("country_code_filter")]
class CountryCodeFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RequestStack $request_stack, protected EntityTypeManagerInterface $entity_type_manager) {
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
      $container->get('request_stack'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Apply WHERE condition to the query dependently on country param.
   */
  public function query(): void {

    $code = $this->request_stack->getCurrentRequest()->get('country');

    if (!$code) {
      return;
    }

    if ($code === 'EU') {
      $this->query->addWhereExpression(0, $this->ensureMyTable() . "__assessment__country.country_target_id IS NULL");
    }

    else {
      $terms = $this->entity_type_manager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'spatial',
          'code' => $code,
        ]);

      if (!$terms) {
        throw new NotFoundHttpException('Country not found.');
      }

      $term = reset($terms);
      $tid = $term->id();
      $field = $this->ensureMyTable() . "__assessment__country.country_target_id";
      $this->query->addWhere(0, $field, $tid, '=');
    }
  }

}
