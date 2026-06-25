<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\link\LinkItemInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes the distribution data into the parent solution or release fields.
 *
 * Distributions are not shown in the site-wide search results. Instead, their
 * data (title, description, URL, licence, etc) is indexed in the parent Search
 * API record, so that searching for a keyword belonging to a distribution will
 * retrieve their parent (release or solution).
 */
#[SearchApiProcessor(
  id: "index_distribution_data",
  label: new TranslatableMarkup("Index distribution data"),
  description: new TranslatableMarkup("Indexes the distribution data into the distribution parent Search API record."),
  stages: [
    "preprocess_index" => 0,
  ],
)]
class IndexDistributionData extends ProcessorPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('file_url_generator'),
    );
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
  public function preprocessIndexItems(array $items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      // Append data from child distributions.
      $this->appendDistributionData($item);
    }
  }

  /**
   * Appends the distribution data to the parent's Search API description field.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The Search API item.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when $item->getOriginalObject() parameter, $load, is TRUE but the
   *   object could not be loaded.
   */
  protected function appendDistributionData(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();

    // Only solutions and releases should receive distribution data.
    if (!$entity instanceof DistributionsParentInterface) {
      return;
    }

    // Get the parent Search API field.
    $sapiField = $item->getField('description');
    if ($sapiField === NULL) {
      return;
    }

    // Iterate over all distributions and append their data to Search API field.
    $langcode = $entity->language()->getId();
    foreach ($entity->getDistributions() as $distribution) {
      $distribution = $this->getTranslation($distribution, $langcode);
      \assert($distribution instanceof DistributionInterface);

      // Add the distribution title.
      $sapiField->addValue($distribution->label());

      // Add the distribution description.
      foreach ($distribution->get('description') as $description) {
        $sapiField->addValue($description->processed);
      }

      foreach ($distribution->getResources() as $resource) {
        if ($resource instanceof LinkItemInterface) {
          $sapiField->addValue($resource->uri);
        }
        elseif ($resource instanceof FileInterface) {
          $sapiField->addValue($this->fileUrlGenerator->generateAbsoluteString($resource->getFileUri()));
        }
      }

      // Add the distribution licence.
      foreach ($distribution->get('field_licence')->referencedEntities() as $licence) {
        $sapiField->addValue($this->getTranslation($licence, $langcode)->label());
      }

      // Add the distribution format.
      foreach ($distribution->get('format')->referencedEntities() as $format) {
        $sapiField->addValue($this->getTranslation($format, $langcode)->label());
      }

      // Add the distribution representation technique.
      foreach ($distribution->get('representation_technique')->referencedEntities() as $technique) {
        $sapiField->addValue($this->getTranslation($technique, $langcode)->label());
      }
    }
  }

  /**
   * Returns the given entity's translation, if it exists.
   */
  private function getTranslation(EntityInterface $entity, string $langcode): EntityInterface {
    return $entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)
      ? $entity->getTranslation($langcode)
      : $entity;
  }

}
