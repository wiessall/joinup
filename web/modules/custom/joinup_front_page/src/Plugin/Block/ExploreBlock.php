<?php

declare(strict_types=1);

namespace Drupal\joinup_front_page\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that allows the user to explore recent content.
 *
 * This block is placed on the front page and shows a swipeable list of recently
 * published content of 4 types: collections, solutions, news and events. The
 * intention is that users can get a quick view of what is new on Joinup.
 *
 * For each content type the 12 most recent items are shown. They are rendered
 * using the `explore_item` view mode which is exclusive for this block.
 *
 * The user can switch between the 4 types by clicking on tabs.
 */
#[Block(
  id: "joinup_front_page_explore_block",
  admin_label: new TranslatableMarkup("Explore block"),
)]
class ExploreBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Node view mode.
   *
   * @var string
   */
  protected string $viewMode = 'explore_item';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $types = [
      'collection' => [
        'id' => 'collections',
        'label' => $this->t('Collections'),
        'plural_type' => $this->t('collections'),
      ],
      'solution' => [
        'id' => 'solutions',
        'label' => $this->t('Solutions'),
        'plural_type' => $this->t('solutions'),
      ],
    ];

    $data = [];
    foreach ($types as $bundle => $info) {
      $data[$info['id']] = [
        'label' => $info['label'],
        'plural_type' => $info['plural_type'],
        'data' => $this->getContentItems($bundle),
        'url' => Url::fromRoute('view.search.page_1', [], [
          'query' => ['f' => ['type:' . $bundle]],
        ])->toString(),
      ];
    }

    return [
      '#theme' => 'explore_block',
      '#data' => $data,
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
        'max-age' => $this->getCacheMaxAge(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // This block shows fixed content which doesn't vary by any context.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Refresh the content at least once every 6 hours.
    return 60 * 60 * 6;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Since this block is shown on the front page it should not be invalidated
    // whenever any of the content types shown in it change. The front page is
    // heavily trafficked and too frequent cache invalidations might affect
    // performance.
    return ['homepage_explore_block'];
  }

  /**
   * Returns the base query for content retrieval.
   *
   * @param string $type
   *   The content type to query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The configured entity query.
   */
  protected function getContentQuery(string $type): QueryInterface {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE);
    return $query->condition('status', 1)
      ->condition('type', $type)
      ->condition('field_state', 'archived', '<>')
      ->sort('changed', 'DESC')
      ->range(0, 12);
  }

  /**
   * Returns the content items for the given bundle.
   *
   * @param string $bundle
   *   The bundle to query.
   *
   * @return array
   *   List of content with view mode "explore_item".
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getContentItems(string $bundle): array {
    $content = [];
    $entity_storage = $this->entityTypeManager->getStorage('node');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Add a custom tag to allow other modules to alter the query without
    // affecting dependencies between modules.
    $tag = "explore_block_{$bundle}";
    $query = $this->getContentQuery($bundle)->addTag($tag);
    $entities = $entity_storage->loadMultiple($query->execute());
    foreach ($entities as $node) {
      $content[] = $view_builder->view($node, $this->viewMode);
    }

    return $content;
  }

}
