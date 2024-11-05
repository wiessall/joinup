<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\search_api\processor;

use Drupal\flag\FlagServiceInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds Interoperable flag field to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "interoperable_flag",
 *   label = @Translation("Interoperable flag"),
 *   description = @Translation("Adds the Interoperable value to the solr."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
class InteroperableFlagProcessor extends ProcessorPluginBase {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flag;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setFlag($container->get('flag'));

    return $processor;
  }

  /**
   * Sets the flag service.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   Flag service.
   *
   * @return $this
   */
  public function setFlag(FlagServiceInterface $flag): self {
    $this->flag = $flag;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Interoperable flag'),
        'description' => $this->t('Adds the flag value to the Solution Solr document.'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['interoperable_flag'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }

    if (!$entity instanceof SolutionInterface) {
      return;
    }

    $isFlagged = $this->isInteroperable($entity);

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'interoperable_flag');
    foreach ($fields as $field) {
      $field->addValue($isFlagged);
    }
  }

  /**
   * Check is solution is interoperable.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $solution
   *   Solution entity.
   *
   * @return bool
   *   True if the solution is interoperable.
   */
  protected function isInteroperable(SolutionInterface $solution): bool {
    $flag = $this->flag->getFlagById('interoperability');
    if (!$flag) {
      return FALSE;
    }

    return $flag->isFlagged($solution);
  }

}
