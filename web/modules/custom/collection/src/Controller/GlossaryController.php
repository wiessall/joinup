<?php

declare(strict_types=1);

namespace Drupal\collection\Controller;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\GlossaryTermInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides controllers for Joinup glossary functionality.
 */
class GlossaryController extends ControllerBase {

  /**
   * Static cache of glossary-term existence, keyed by collection node ID.
   *
   * @var bool[]
   */
  protected static array $hasGlossaryTerms = [];

  public function __construct(
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
    );
  }

  /**
   * Provides an access callback for the Glossary route.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(CollectionInterface $node): AccessResultInterface {
    $tags = Cache::buildTags('og-group-content', $node->getCacheTagsToInvalidate());
    return AccessResult::allowedIf($this->hasGlossaryTerms($node))
      ->addCacheTags($tags);
  }

  /**
   * Provides a controller for the Glossary route.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   * @param string|null $letter
   *   (optional) An optional letter to filter the glossary on.
   *
   * @return array
   *   The output render array.
   */
  public function glossary(CollectionInterface $node, ?string $letter = NULL): array {
    $navigator = [
      '#theme' => 'glossary_navigator',
      '#url' => Url::fromRoute('entity.node.glossary', [
        'node' => $node->id(),
      ])->toString(),
      '#letters' => $this->getGlossaryTermLetters($node),
      '#current' => $letter,
    ];

    $view = Views::getView('glossary')->executeDisplay('main', [
      $node->id(),
      $letter,
    ]);
    unset($view['#cache']['max-age']);

    return [
      [
        $navigator,
        $view,
        $navigator,
      ],
      '#cache' => [
        'tags' => Cache::buildTags('og-group-content', $node->getCacheTagsToInvalidate()),
      ],
    ];
  }

  /**
   * Returns the glossary page title.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The translated title.
   */
  public function title(CollectionInterface $node): MarkupInterface {
    return $this->t('@group glossary', ['@group' => $node->label()]);
  }

  /**
   * Finds out if the collection has glossary term entries.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   *
   * @return bool
   *   If the collection has glossary term entries.
   */
  protected function hasGlossaryTerms(CollectionInterface $node): bool {
    return static::$hasGlossaryTerms[$node->id()] ??= (bool) $this->glossaryTermQuery($node)->range(0, 1)->execute();
  }

  /**
   * Returns a list of unique uppercase letters extracted from glossary terms.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   *
   * @return string[]
   *   Grouped list of the first letter of each glossary term, transformed to
   *   uppercase.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the node entity plugin definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when the node entity type is not defined.
   */
  protected function getGlossaryTermLetters(CollectionInterface $node): array {
    $nids = $this->glossaryTermQuery($node)->execute();
    if (!$nids) {
      return [];
    }

    $letters = [];
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($nids) as $term) {
      \assert($term instanceof GlossaryTermInterface);
      $translation = $this->entityRepository->getTranslationFromContext($term);
      $letters[] = $translation->getGlossaryLetter();
    }

    $letters = array_unique($letters);
    sort($letters);
    return $letters;
  }

  /**
   * Builds a query for the published glossary terms of a collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The collection entity.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The unexecuted entity query.
   */
  private function glossaryTermQuery(CollectionInterface $node): QueryInterface {
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'glossary')
      ->condition('og_audience', $node->id())
      ->condition('status', TRUE);
  }

}
