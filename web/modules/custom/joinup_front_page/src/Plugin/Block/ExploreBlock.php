<?php

declare(strict_types=1);

namespace Drupal\joinup_front_page\Plugin\Block;

use Drupal\comment\CommentInterface;
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
 * A final tab lists the content that has most recently been commented on. That
 * tab is not restricted to a single bundle, but its items use the same view
 * mode as the others so that the tiles look alike: `explore_item` is defined
 * for every bundle that accepts comments as well.
 *
 * The user can switch between the types by clicking on tabs.
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

  /**
   * Number of items shown per tab.
   */
  protected const int ITEMS_PER_TAB = 12;

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

    // The recently commented tab deliberately carries no "see more" URL: search
    // indexes content, not comments, so there is no listing that continues this
    // one. The template omits the button when the URL is absent.
    $data['user_comments'] = [
      'label' => $this->t('User Comments'),
      'data' => $this->getRecentlyCommentedItems(),
    ];

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

  /**
   * Returns the content that has most recently been commented on.
   *
   * Every bundle that accepts comments is eligible: the query filters on the
   * commented entity type rather than on a list of bundles or comment fields,
   * so a bundle that gains a comment field later is picked up without a code
   * change. It does need an `explore_item` view display of its own, or it falls
   * back to the default one and renders as a full page inside the tile.
   *
   * @return array
   *   Render arrays for the commented content, most recently commented first.
   *   Each one carries the latest comment in `#joinup_latest_comment`.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRecentlyCommentedItems(): array {
    $comment_storage = $this->entityTypeManager->getStorage('comment');

    // One row per commented node, holding the time of its newest comment.
    // Deliberately over-fetched: the aggregate cannot join to the node table,
    // so unpublished and archived content is only dropped further down and
    // would otherwise eat into the number of tiles shown.
    $rows = $comment_storage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('status', CommentInterface::PUBLISHED)
      ->condition('entity_type', 'node')
      ->groupBy('entity_id')
      ->aggregate('created', 'MAX')
      ->sortAggregate('created', 'MAX', 'DESC')
      ->range(0, static::ITEMS_PER_TAB * 3)
      ->addTag('explore_block_user_comments')
      ->execute();

    if (!$rows) {
      return [];
    }

    // Keyed by node ID, ordered newest comment first.
    $newest_comment_time = [];
    foreach ($rows as $row) {
      $newest_comment_time[(int) $row['entity_id']] = (int) $row['created_max'];
    }

    // Restrict to content that is actually on display, mirroring the filters
    // the single-bundle tabs apply.
    $visible = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('nid', array_keys($newest_comment_time), 'IN')
      ->condition('field_state', 'archived', '<>')
      ->execute();
    $visible = array_map('intval', array_values($visible));

    // The node query returns its own order; the comment order is what matters.
    $nids = array_values(array_filter(
      array_keys($newest_comment_time),
      static fn(int $nid): bool => in_array($nid, $visible, TRUE),
    ));
    $nids = array_slice($nids, 0, static::ITEMS_PER_TAB);

    if (!$nids) {
      return [];
    }

    $newest_comments = $this->getNewestComments($nids, $newest_comment_time);

    $content = [];
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    foreach ($nids as $nid) {
      if (!isset($nodes[$nid], $newest_comments[$nid])) {
        continue;
      }
      $node = $this->entityRepository->getTranslationFromContext($nodes[$nid]);
      $build = $view_builder->view($node, $this->viewMode);
      // Consumed by joinup_front_page_preprocess_node(), which turns it into
      // the "See comment" link and the comment date in the tile footer.
      $build['#joinup_latest_comment'] = $newest_comments[$nid];
      $content[] = $build;
    }

    return $content;
  }

  /**
   * Loads the newest comment of each of the given nodes.
   *
   * @param int[] $nids
   *   The node IDs to load the newest comment for.
   * @param int[] $newest_comment_time
   *   Creation timestamp of the newest comment, keyed by node ID.
   *
   * @return \Drupal\comment\CommentInterface[]
   *   The newest comment, keyed by node ID. A node without a matching comment
   *   is absent from the array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getNewestComments(array $nids, array $newest_comment_time): array {
    $comment_storage = $this->entityTypeManager->getStorage('comment');

    // Narrowed by the timestamps already established above, so this loads a
    // handful of comments rather than every comment on every node.
    $cids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', CommentInterface::PUBLISHED)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $nids, 'IN')
      ->condition('created', array_values(array_intersect_key($newest_comment_time, array_flip($nids))), 'IN')
      ->execute();

    $newest = [];
    foreach ($comment_storage->loadMultiple($cids) as $comment) {
      assert($comment instanceof CommentInterface);
      $nid = (int) $comment->getCommentedEntityId();
      // A timestamp that is the newest for one node may be an older comment on
      // another, so match the pair rather than trusting the timestamp alone.
      // Cast: storage hands timestamps back as strings.
      if (($newest_comment_time[$nid] ?? NULL) !== (int) $comment->getCreatedTime()) {
        continue;
      }
      // Two comments can share the newest timestamp; the later one wins.
      if (!isset($newest[$nid]) || $comment->id() > $newest[$nid]->id()) {
        $newest[$nid] = $comment;
      }
    }

    return $newest;
  }

}
