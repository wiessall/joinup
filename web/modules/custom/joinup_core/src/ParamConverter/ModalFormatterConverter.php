<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\ParamConverter;

use Drupal\Core\ParamConverter\EntityConverter;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for field view modal routes.
 *
 * This is required to keep the copied route definition compatible with entity
 * access checks.
 */
class ModalFormatterConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if ($value !== 'modal_formatter' || !isset($defaults['entity'])) {
      return NULL;
    }

    $definition['type'] = str_replace('modal_formatter:', 'entity:', $definition['type']);
    $defaults[$name] = $defaults['entity'];

    return parent::convert($defaults['entity'], $definition, $name, $defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && str_starts_with($definition['type'], 'modal_formatter:');
  }

}
