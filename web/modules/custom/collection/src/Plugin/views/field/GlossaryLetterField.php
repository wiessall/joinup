<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\views\field;

use Drupal\collection\Entity\GlossaryTermInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a glossary term's index letter, in the active language.
 *
 * @see \Drupal\collection\Entity\GlossaryTerm::getGlossaryLetter()
 */
#[ViewsField('glossary_letter')]
class GlossaryLetterField extends FieldPluginBase {

  /**
   * The entity repository.
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // The value is derived from the entity, no query field is needed.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $entity = $this->getEntity($values);
    $entity = $this->entityRepository->getTranslationFromContext($entity);
    return $entity instanceof GlossaryTermInterface ? $entity->getGlossaryLetter() : '';
  }

}
