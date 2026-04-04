<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\GlossaryTermTrait;
use Drupal\collection\Exception\MissingCollectionException;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\og\OgContextInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps glossary terms with links to their definition page.
 */
#[Filter(
  id: "collection_glossary",
  title: new TranslatableMarkup("Collection glossary"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Replaces glossary terms with their link version."),
)]
class Glossary extends FilterBase implements ContainerFactoryPluginInterface {

  use GlossaryTermTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly OgContextInterface $ogContext,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerInterface $logger,
    protected readonly AliasRepositoryInterface $aliasRepository,
    protected readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'cache.memory')]
    protected readonly CacheBackendInterface $cache,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);

    $collection = $this->getCollection();
    // A collection context cannot be detected.
    if (!isset($collection)) {
      return $result;
    }

    $cache_metadata = (new CacheableMetadata())
      // Invalidate the cache when collection glossary settings are changing.
      ->addCacheableDependency($collection->get('collection_settings')->entity);

    $map = $this->getReplacementsMap($collection, $cache_metadata);

    // This collection has no glossary term entries.
    if (empty($map)) {
      return $result->addCacheableDependency($cache_metadata);
    }

    $escaped_replacements = array_map(function (string $replacement): string {
      return preg_quote($replacement, '/');
    }, array_keys($map));
    $pattern = '/\b(' . implode('|', $escaped_replacements) . ')\b/i';

    // First, do a bird-eye check for glossary terms so that we avoid heavy
    // processing if the text contains no term.
    if (!preg_match($pattern, $text)) {
      return $result->addCacheableDependency($cache_metadata);
    }

    $link_only_first = $collection->getGlossarySettings()['link_only_first'];

    $document = Html::load($text);
    $text_nodes = (new \DOMXPath($document))->evaluate("//text()");
    foreach ($text_nodes as $text_node) {
      // Avoid adding nested links.
      if ($this->isLinkText($text_node)) {
        continue;
      }

      $text_parts = preg_split($pattern, $text_node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
      $parent_node = $text_node->parentNode;

      foreach ($text_parts as $text_part) {
        $text_part_lowercased = \mb_strtolower($text_part);
        if (!isset($map[$text_part_lowercased])) {
          $parent_node->insertBefore($document->createTextNode($text_part), $text_node);
          continue;
        }

        // @todo Consider replacing the link with a JavaScript tooltip.
        $link = $document->createElement('a', $text_part);
        $link->setAttribute('href', $map[$text_part_lowercased]['path']);
        $link->setAttribute('class', 'glossary-term');
        $link->setAttribute('title', $map[$text_part_lowercased]['summary']);
        $parent_node->insertBefore($link, $text_node);

        // If this collection was configured to replace only the first
        // occurrence of the term, remove this match from the replacement list.
        if ($link_only_first) {
          unset($map[$text_part_lowercased]);
        }
      }
      $parent_node->removeChild($text_node);
    }

    return $result
      ->setProcessedText($document->saveHTML())
      ->addCacheableDependency($cache_metadata);
  }

  /**
   * Finds-out if a piece of text is inside a HTML link.
   *
   * @param \DOMText $text_node
   *   The text node to be checked.
   *
   * @return bool
   *   If the given text node is inside a HTML link.
   */
  protected function isLinkText(\DOMText $text_node): bool {
    $node = $text_node;
    do {
      $node = $node->parentNode;
      if (!$node->parentNode) {
        return FALSE;
      }
    } while ($node->tagName !== 'a');
    return TRUE;
  }

  /**
   * Builds and returns a replacement map.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection for which to return the replacements map.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata to be attached to the returned replacements map.
   *
   * @return array<string, array{path: string, summary: string}>
   *   An associative array keyed by the glossary term or synonym and having an
   *   associative with two keys as values:
   *     - path: The path to the glossary term.
   *     - summary: A summary to be used as tooltip.
   */
  protected function getReplacementsMap(CollectionInterface $collection, CacheableMetadata $cache_metadata): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $cid = "joinup_group.collection_glossary_map.{$collection->id()}.$langcode";

    if ($cached = $this->cache->get($cid)) {
      $cache_metadata->addCacheTags($cached->tags);
      return $cached->data;
    }

    // Invalidate the cache when the glossary terms are changing.
    $tags = Cache::buildTags('og-group-content', $collection->getCacheTagsToInvalidate());

    $results = $this->entityTypeManager->getStorage('node')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'glossary')
      ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $collection->id())
      ->condition('status', TRUE)
      ->groupBy('nid')
      ->groupBy('title')
      ->groupBy('field_glossary_synonyms')
      ->groupBy('field_glossary_definition.value')
      ->groupBy('field_glossary_definition.summary')
      ->groupBy('field_glossary_definition.format')
      ->execute();

    if (!$results) {
      return [];
    }

    // Build a list of path aliases for the given list of node IDs.
    $system_paths = array_map(
      fn(int|string $nid): string => "/node/$nid",
      array_unique(array_column($results, 'nid')),
    );
    $path_aliases = $this->aliasRepository->preloadPathAlias($system_paths, $langcode);

    $map = [];
    foreach ($results as [
      'nid' => $nid,
      'title' => $title,
      'field_glossary_synonyms' => $synonym,
      'field_glossary_definition' => $definition,
      'field_glossary_definition_summary' => $summary,
      'field_glossary_definition_format' => $format,
    ]) {
      $system_path = "/node/$nid";
      $data = [
        'summary' => $this->buildSummary($definition, $summary, $format),
        'path' => $path_aliases[$system_path] ?? $system_path,
      ];
      $tags = Cache::mergeTags($tags, ["node:$nid"]);

      // Ensure case-insensitive search.
      $title = \mb_strtolower($title);

      if (!isset($map[$title])) {
        $map[$title] = $data;
      }

      // Link also the synonyms, if any.
      if ($synonym) {
        $synonym = \mb_strtolower($synonym);
        if (!isset($map[$synonym])) {
          $map[$synonym] = $data;
        }
      }
    }

    // The reverse key sort ensures that, when terms overlap, for example,
    // "exchange", "exchange currency", and "exchange platform", the longer
    // terms are replaced first, and the shorter terms last.
    krsort($map);

    $this->cache->set(cid: $cid, data: $map, tags: $tags);

    return $map;
  }

  /**
   * Retrieves the collection from the request context.
   *
   * This is not ideal, but text filters have no direct way to retrieve the
   * context they belong to. We do not have access to the field that contains
   * the text.
   *
   * @return \Drupal\collection\Entity\CollectionInterface|null
   *   The collection, or NULL if no collection could be derived from the
   *   context.
   *
   * @see https://www.drupal.org/project/drupal/issues/226963
   */
  protected function getCollection(): ?CollectionInterface {
    if ($group = $this->ogContext->getGroup()) {
      // Another kind of group? Maybe a solution that is collection content?
      if ($group instanceof CollectionContentInterface) {
        try {
          $group = $group->getCollection();
        }
        catch (MissingCollectionException $e) {
          // The content is orphaned. Log an error but allow the request to
          // continue, this is not fatal.
          $this->logger->error('Collection could not be retrieved from entity of type %type and ID %id', [
            '%type' => $group->getEntityTypeId(),
            '%id' => $group->id(),
          ]);
        }
      }
    }

    if ($group instanceof CollectionInterface) {
      return $group;
    }

    return NULL;
  }

}
