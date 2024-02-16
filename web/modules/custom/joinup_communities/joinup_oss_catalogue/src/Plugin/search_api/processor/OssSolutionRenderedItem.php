<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds field containing the rendered item for all languages.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Property\RenderedItemProperty
 *
 * @SearchApiProcessor(
 *   id = "oss_solution_rendered_item",
 *   label = @Translation("Oss Solution rendered item"),
 *   description = @Translation("Adds field containing the rendered item for
 *   all languages."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class OssSolutionRenderedItem extends ProcessorPluginBase {

  use LoggerTrait;

  /**
   * View mode used to render entity.
   *
   * @var string
   */
  protected string $viewMode = 'search_index';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer to use.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->setEntityTypeManager($container->get('entity_type.manager'));
    $plugin->setRenderer($container->get('renderer'));
    $plugin->setLogger($container->get('logger.channel.search_api'));

    return $plugin;
  }

  /**
   * Sets the renderer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager): self {
    $this->entityTypeManager = $entityTypeManager;
    return $this;
  }

  /**
   * Sets the renderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The new renderer.
   *
   * @return $this
   */
  public function setRenderer(RendererInterface $renderer): self {
    $this->renderer = $renderer;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'node') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof OssSolutionInterface) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'oss_solution_rendered_item');
    foreach ($fields as $field) {
      try {
        $this->generateMarkupForField($entity, $field);
      }
      catch (\Throwable $e) {
        $variables = [
          '%item_id' => $item->getId(),
          '%view_mode' => $this->viewMode,
          '%index' => $this->index->label(),
        ];
        $this->logException($e, '%type while trying to render item %item_id with view mode %view_mode for search index %index: @message in %function (line %line of %file).', $variables);
      }
    }
  }

  /**
   * Generates markup for field.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $entity
   *   Oss solution entity.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search API field.
   */
  protected function generateMarkupForField(OssSolutionInterface $entity, FieldInterface $field): void {
    $viewBuilder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());

    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);

      $build = $viewBuilder->view($translation, $this->viewMode, $langcode);
      if (!$build) {
        return;
      }

      $markup = (string) $this->renderer->renderPlain($build);
      $field->addValue($markup);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Oss Solution rendered item'),
        'description' => $this->t('The complete HTML which would be displayed when viewing the item'),
        'type' => 'search_api_html',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['oss_solution_rendered_item'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

}
