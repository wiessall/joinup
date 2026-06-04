<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\sti_rdf\Service\AnnotationLibrary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders the per-collection faceted search.
 *
 * No per-collection config: the schema and facet values come from whatever
 * has been indexed into annotation_value. The form is a regular Drupal form
 * with method=GET so filter state lives in the URL and is shareable.
 */
final class CollectionSearchController extends ControllerBase {

  /** Cap on rendered results per page. */
  private const PAGE_SIZE = 25;

  public function __construct(
    protected readonly AnnotationLibrary $library,
    protected $entityTypeManager,
    protected RequestStack $requestStack,
    protected $formBuilder,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('sti_rdf.annotation_library'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('form_builder'),
    );
  }

  /**
   * Title callback.
   */
  public function title(string $collection): string {
    return (string) $this->t('@collection — Search', ['@collection' => $collection]);
  }

  /**
   * Page callback.
   */
  public function page(string $collection): array {
    $schema = $this->library->getSchema($collection);

    if (empty($schema)) {
      return [
        '#markup' => $this->t('No annotated records found for collection %c.', ['%c' => $collection]),
        '#cache' => [
          'tags' => ['annotation_value_list'],
          'contexts' => ['url.path'],
        ],
      ];
    }

    // Extract active filters from query params, restricted to known schema
    // fields so we don't leak arbitrary strings into our queries.
    $request = $this->requestStack->getCurrentRequest();
    $query = $request->query->all();
    $filters = [];
    foreach ($schema as $fieldName) {
      if (!isset($query[$fieldName])) {
        continue;
      }
      $vals = (array) $query[$fieldName];
      // Drop empty checkbox entries Drupal sometimes emits.
      $vals = array_values(array_filter($vals, static fn($v) => $v !== '' && $v !== NULL));
      if (!empty($vals)) {
        $filters[$fieldName] = $vals;
      }
    }

    $form = $this->formBuilder->getForm(
      'Drupal\sti_rdf\Form\CollectionSearchForm',
      $collection,
      $schema,
      $filters,
    );

    $targetIds = $this->library->findTargetEntityIds($collection, $filters);
    $total = count($targetIds);

    $build = [
      '#cache' => [
        'tags' => ['annotation_value_list'],
        'contexts' => ['url.path', 'url.query_args'],
      ],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('@n result(s) in @collection', [
          '@n' => $total,
          '@collection' => $collection,
        ]),
      ],
      'form' => $form,
      'results' => $this->buildResults($collection, $targetIds),
    ];

    return $build;
  }

  /**
   * Renders the matched target entities, paginated.
   */
  private function buildResults(string $collection, array $targetIds): array {
    if (empty($targetIds)) {
      return [
        '#markup' => '<p>' . $this->t('No matches.') . '</p>',
      ];
    }

    $entityType = $this->library->getTargetEntityType($collection) ?? 'node';

    // Naive pagination via ?page=N. Drupal's pager_default service expects a
    // DB query; here we have an in-memory id list, so we slice manually.
    $page = max(0, (int) $this->requestStack->getCurrentRequest()->query->get('page', 0));
    $slice = array_slice($targetIds, $page * self::PAGE_SIZE, self::PAGE_SIZE);

    if (empty($slice)) {
      return [
        '#markup' => '<p>' . $this->t('No more results.') . '</p>',
      ];
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $viewBuilder = $this->entityTypeManager->getViewBuilder($entityType);
    }
    catch (\Exception $e) {
      return [
        '#markup' => '<p>' . $this->t('Unable to render results for entity type %t.', ['%t' => $entityType]) . '</p>',
      ];
    }

    $entities = $storage->loadMultiple($slice);
    $items = [];
    foreach ($entities as $entity) {
      $items[] = $viewBuilder->view($entity, 'teaser');
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collection-search-results-wrapper']],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['collection-search-results']],
      ],
    ];

    $totalPages = (int) ceil(count($targetIds) / self::PAGE_SIZE);
    if ($totalPages > 1) {
      $build['pager'] = $this->buildPager($page, $totalPages);
    }

    return $build;
  }

  /**
   * Builds a simple prev/next pager render array.
   */
  private function buildPager(int $page, int $totalPages): array {
    $params = $this->requestStack->getCurrentRequest()->query->all();

    $pager = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collection-search-pager']],
    ];

    if ($page > 0) {
      $params['page'] = $page - 1;
      $pager['prev'] = [
        '#type' => 'link',
        '#title' => $this->t('« Previous'),
        '#url' => Url::fromRoute('<current>', [], ['query' => $params]),
      ];
    }
    $pager['status'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Page @c of @t', [
        '@c' => $page + 1,
        '@t' => $totalPages,
      ]),
      '#attributes' => ['class' => ['pager-status']],
    ];
    if ($page + 1 < $totalPages) {
      $params['page'] = $page + 1;
      $pager['next'] = [
        '#type' => 'link',
        '#title' => $this->t('Next »'),
        '#url' => Url::fromRoute('<current>', [], ['query' => $params]),
      ];
    }
    return $pager;
  }

  /**
   * Admin endpoint: rebuild the EAV table.
   */
  public function reindex(): array {
    $count = \Drupal::service('sti_rdf.annotation_indexer')->reindexAll();
    \Drupal::messenger()->addStatus($this->t('Re-indexed @n annotations.', ['@n' => $count]));
    return [
      '#markup' => $this->t('Done. @n annotations re-indexed.', ['@n' => $count]),
      '#cache' => ['max-age' => 0],
    ];
  }

}
