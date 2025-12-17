<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentValidator;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates country code taxonomy term.
 */
#[ViewsArgumentValidator(
  id: "country_code_validator",
  title: new TranslatableMarkup("Taxonomy country code"),
  entity_type: "taxonomy_term",
)]
final class CountryCodeValidator extends ArgumentValidatorPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg): bool {
    if ($arg === 'EU') {
      return TRUE;
    }

    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'spatial',
        'code' => $arg,
      ]);

    return !empty($terms);
  }

}
