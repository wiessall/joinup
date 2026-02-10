<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes unpublished entities from the index.
 */
#[SearchApiProcessor(
  id: "joinup_entity_status",
  label: new TranslatableMarkup("Joinup entity status"),
  description: new TranslatableMarkup("Exclude unpublished content and users."),
  stages: [
    "alter_items" => 0,
  ],
)]
class JoinupEntityStatus extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    return $processor;
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): self {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    $supported_entity_types = ['node', 'user'];
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), $supported_entity_types)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'inverse' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['inverse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inverse'),
      '#description' => $this->t('If checked, unpublished entities will be indexed instead.'),
      '#default_value' => $this->configuration['inverse'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $item_id => $item) {
      assert($item instanceof ItemInterface);
      $object = $item->getOriginalObject()->getValue();
      $inverse = $this->getConfiguration()['inverse'];
      $enabled = TRUE;
      if ($object instanceof NodeInterface && $object instanceof GroupContentInterface) {
        $enabled = $object->isPublished();
        // The entity can be published only if the parent entity is published.
        // Load the parent from the entity storage cache rather than relying
        // on the copy that is present in $object->og_audience->entity since
        // this might be stale. This ensures that if the parent has been
        // published in this request we will act on the actual updated state.
        if ($enabled && $object instanceof GroupContentInterface && $object->hasGroup()) {
          $enabled = FALSE;
          try {
            $parent = $this->entityTypeManager->getStorage('node')->load($object->getGroupId());
            if ($parent instanceof EntityPublishedInterface && $parent->isPublished()) {
              $enabled = TRUE;
            }
          }
          catch (MissingGroupException) {
          }
        }
      }
      elseif ($object instanceof UserInterface) {
        $enabled = $object->isActive();
      }
      elseif ($object instanceof GroupInterface) {
        $enabled = $object->isPublished();
      }

      $enabled = $inverse ? !$enabled : $enabled;
      if (!$enabled) {
        unset($items[$item_id]);
      }
    }
  }

}
