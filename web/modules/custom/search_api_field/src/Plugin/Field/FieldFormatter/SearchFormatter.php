<?php

declare(strict_types=1);

namespace Drupal\search_api_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_field\Plugin\Field\FieldType\SearchItemInterface;
use Drupal\search_api_field\Plugin\FilterPluginManagerInterface;
use Drupal\topic\Entity\TopicInterface;
use Drupal\topic\Service\TopicServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'search_api_field' formatter.
 */
#[FieldFormatter(
  id: 'search_api_field',
  label: new TranslatableMarkup('Search'),
  field_types: [
    'search_api_field',
  ],
)]
class SearchFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Request $request,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly ParseModePluginManager $parseModeManager,
    protected readonly FilterPluginManagerInterface $filterPluginManager,
    protected readonly LoggerChannelInterface $logger,
    protected readonly PagerManagerInterface $pagerManager,
    protected readonly TopicServiceInterface $topicService,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('language_manager'),
      $container->get('plugin.manager.search_api.parse_mode'),
      $container->get('plugin.manager.search_api_field.filter'),
      $container->get('logger.channel.search_api'),
      $container->get('pager.manager'),
      $container->get('topic')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Avoid infinite recursion when a search node is shown as a result.
    $entity = $items->getEntity();
    if ($entity instanceof MetaEntityInterface) {
      $entity = $entity->getTargetEntity();
    }
    if ($entity->search_api_field_do_not_recurse) {
      return [];
    }

    // At the moment, this formatter supports only single-value fields.
    $item = $items->first();
    if (empty($item)) {
      return [];
    }
    $settings = $item->value;
    // Bail out if the field is disabled.
    if (empty($settings['enabled'])) {
      return [];
    }

    // Build the pager. The $element will ensure that a unique identifier is
    // assigned to each pager. This is required because the search_api_field
    // is a field that can appear anywhere it can be attached. For example, a
    // search_api_field might be directly attached to an entity but also as a
    // paragraph. Without this, all pagers would change all fields.
    static $element = 0;

    try {
      $query = $this->getSearchQuery($settings, $item, $element);
      $result = $query->execute();
      // Extract the limit value from the query as it might have been altered
      // doing execution.
      $render = $this->renderSearchResults($result, (int) $query->getOption('limit'), $element);
    }
    catch (SearchApiException $exception) {
      $this->logger->log(RfcLogLevel::ERROR, 'Failed to execute query with error "@message".', Error::decodeException($exception));
      return [
        // Do not cache this page as the exception could be temporary. This
        // is in line with the uncacheability of a request when an exception
        // occurs and it is not cached.
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    $this->mergeQueryCacheMetadata($render, $query);

    $archived_items = $this->getArchivedQuery($settings, $item);

    // Add some information about the field.
    // @see \Drupal\Core\Field\FormatterBase::view()
    $render += [
      '#entity_type' => $entity->getEntityTypeId(),
      '#bundle' => $entity->bundle(),
      '#field_name' => $this->fieldDefinition->getName(),
      '#entity' => $entity,
      '#view_mode' => $this->viewMode,
      '#archived_items' => $archived_items->execute()->getResultCount(),
    ];

    $this->mergeQueryCacheMetadata($render, $archived_items);

    $element++;

    return $render;
  }

  /**
   * Generates the search query given the field settings.
   *
   * @param array $settings
   *   The settings configured in the field.
   * @param \Drupal\search_api_field\Plugin\Field\FieldType\SearchItemInterface $item
   *   The field item.
   * @param int $element
   *   The page element number.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The prepared query.
   */
  protected function getSearchQuery(array $settings, SearchItemInterface $item, int $element): QueryInterface {
    $field_definition = $this->fieldDefinition;
    $entity = $item->getEntity();
    if ($entity instanceof MetaEntityInterface) {
      $entity = $entity->getTargetEntity();
    }
    $options = [
      'limit' => !empty($settings['limit']) ? $settings['limit'] : 10,
      'offset' => $this->request->query->get('page') !== NULL ? $this->pagerManager->findPage($element) * (int) $settings['limit'] : 0,
      'search_api_field entity' => $entity,
      'search_api_field item' => $item,
    ];
    $query = $this->getSearchApiIndex()->query($options);
    $query->setLanguages([$this->languageManager->getCurrentLanguage()->getId()]);
    $query->setSearchId($field_definition->id());
    $query->setParseMode($this->parseModeManager->createInstance('direct'));

    if (!empty($settings['query_presets'])) {
      $this->applyPresets($query, $settings['query_presets']);
    }

    if (!empty($settings['query_builder'])) {
      $this->applyQueryBuilderConfiguration($query, $settings['query_builder']);
    }

    $hooks = [
      'search_api_field',
      'search_api_field_' . $field_definition->getName(),
    ];
    foreach ($hooks as $hook) {
      $query->addTag($hook);
    }

    return $query;
  }

  /**
   * Generates the search query to get number of archived content.
   *
   * @param array $settings
   *   The settings configured in the field.
   * @param \Drupal\search_api_field\Plugin\Field\FieldType\SearchItemInterface $item
   *   The field item.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The prepared query.
   */
  protected function getArchivedQuery(array $settings, SearchItemInterface $item): QueryInterface {
    $field_definition = $this->fieldDefinition;
    $entity = $item->getEntity();
    if ($entity instanceof MetaEntityInterface) {
      $entity = $entity->getTargetEntity();
    }
    $options = [
      'search_api_field entity' => $entity,
      'search_api_field item' => $item,
      'search_api_field archived' => TRUE,
    ];
    $query = $this->getSearchApiIndex()->query($options);
    $query->setSearchId($field_definition->id() . '.archived');
    $query->setParseMode($this->parseModeManager->createInstance('direct'));

    if (!empty($settings['query_presets'])) {
      $this->applyPresets($query, $settings['query_presets']);
    }

    if (!empty($settings['query_builder'])) {
      $this->applyQueryBuilderConfiguration($query, $settings['query_builder']);
    }
    $hooks = [
      'search_api_field',
      'search_api_field_' . $field_definition->getName(),
      'search_api_field_' . $field_definition->getName() . '_archived',
    ];
    foreach ($hooks as $hook) {
      $query->addTag($hook);
    }
    return $query;
  }

  /**
   * Builds a renderable array for the search results.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   The query results object.
   * @param int $limit
   *   The number of results to show for each page.
   * @param int $element
   *   The page element number.
   *
   * @return array
   *   The render array for the search results.
   */
  protected function renderSearchResults(ResultSetInterface $result, int $limit, int $element): array {
    $view_mode_settings = $this->fieldDefinition->getSetting('view_modes');

    $results = [];
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($result->getResultItems() as $item) {
      try {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $item->getOriginalObject()->getValue();
      }
      catch (SearchApiException $e) {
        $entity = NULL;
      }
      // Search results might be stale, so we check if the entity has been
      // found in the system.
      if (!$entity) {
        continue;
      }
      if (!$entity->access('view')) {
        continue;
      }

      // Avoid recursions when an entity contains another search field.
      $entity->search_api_field_do_not_recurse = TRUE;

      // Use the view mode configured in the field type settings. Skip
      // entities whose bundle is not configured in the view modes settings
      // since they are not intended to appear in the listing.
      $entity_type = $entity->getEntityTypeId();
      $entity_bundle = $entity->bundle();
      $datasource_id = 'entity:' . $entity_type;
      if (empty($view_mode_settings[$datasource_id][$entity_bundle])) {
        continue;
      }
      $view_mode = $view_mode_settings[$datasource_id][$entity_bundle];

      $results[] = [
        '#theme' => 'search_api_field_result',
        '#item' => $this->entityTypeManager->getViewBuilder($entity_type)->view($entity, $view_mode),
        '#entity' => $entity,
      ];
    }

    $build = [
      '#theme' => 'search_api_field',
    ];

    if (!empty($results)) {
      $build += [
        '#search_title' => [
          '#markup' => $this->t('Search results'),
        ],
        '#no_of_results' => [
          '#markup' => $this->formatPlural($result->getResultCount(), '1 result found', '@count results found'),
        ],
        '#results' => $results,
      ];

      $build['#pager'] = [
        '#type' => 'pager',
        '#element' => $element,
      ];
      $this->pagerManager->createPager($result->getResultCount(), $limit, $element);
    }
    else {
      $build['#no_results_found'] = [
        '#markup' => $this->t('Your search yielded no results.'),
      ];

      $build['#search_help'] = [
        '#markup' => $this->t('<ul>
<li>Check if your spelling is correct.</li>
<li>Remove quotes around phrases to search for each word individually. <em>bike shed</em> will often show more results than <em>&quot;bike shed&quot;</em>.</li>
<li>Consider loosening your query with <em>OR</em>. <em>bike OR shed</em> will often show more results than <em>bike shed</em>.</li>
</ul>'),
      ];
    }

    $build['#cache'] = [
      'tags' => [],
      'max-age' => -1,
      'contexts' => ['url.path'],
    ];

    return $build;
  }

  /**
   * Applies query presets configured in the field instance.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query object.
   * @param string $presets
   *   The presets string.
   */
  protected function applyPresets(QueryInterface $query, string $presets): void {
    $list = explode("\n", $presets);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $line) {
      $matches = [];
      // The format of each line can be:
      // - property|value
      // - property|value|operator
      // Property is the name of the Solr field we want to use to filter.
      // Operator is optional.
      if (preg_match('/([^\|]*)\|([^\|]*)(?:\|(.*))?/', $line, $matches)) {
        $field = trim($matches[1]);
        $value = trim($matches[2]);
        $operator = !empty($matches[3]) ? trim($matches[3]) : '=';

        // Handle the IN operator: in this case the value can be a
        // comma-separated list.
        if ($operator === 'IN') {
          $value = explode(',', $value);
          $value = array_map(function ($item) use ($field) {
            return $this->convertValuePreset(trim($item), $field);
          }, $value);
          $value = array_filter($value);
        }
        else {
          $value = $this->convertValuePreset($value, $field);
        }

        if ($value) {
          $query->addCondition($field, $value, $operator);
        }
      }
    }
  }

  /**
   * Converts the given field value preset.
   *
   * @param mixed $value
   *   The preset value.
   * @param string $field
   *   The Search API field name.
   *
   * @return mixed
   *   The converted value.
   */
  protected function convertValuePreset(mixed $value, string $field): mixed {
    if ($field === 'topic') {
      $entity = $this->topicService->getTopicByName($value);
      $value = ($entity instanceof TopicInterface) ? $entity->id() : NULL;
    }

    return $value;
  }

  /**
   * Applies the query builder filters configured in the field instance.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query object.
   * @param array $configuration
   *   The query builder configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function applyQueryBuilderConfiguration(QueryInterface $query, array $configuration): void {
    $or = $query->createConditionGroup('OR');
    foreach ($configuration['filters'] as $plugin_config) {
      if (!is_array($plugin_config) || empty($plugin_config['plugin'])) {
        continue;
      }

      /** @var \Drupal\search_api_field\Plugin\FilterPluginInterface $plugin */
      $plugin = $this->filterPluginManager->createInstance($plugin_config['plugin'], $plugin_config);
      $plugin->applyFilter($or);
    }
    if (!$or->isEmpty()) {
      $query->addConditionGroup($or);
    }
  }

  /**
   * Merges Search API query cache metadata with the given render array.
   *
   * @param array $build
   *   The search result render array.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search API query.
   */
  protected function mergeQueryCacheMetadata(array &$build, QueryInterface $query): void {
    if ($query instanceof CacheableDependencyInterface) {
      $build['#cache'] = [
        'tags' => Cache::mergeTags(
          Cache::mergeTags(
            $build['#cache']['tags'],
            ["search_api_list:{$query->getIndex()->id()}"],
            ["search_api_list:{$query->getSearchId()}"]
          ),
          $query->getCacheTags()
        ),
        'max-age' => Cache::mergeMaxAges($build['#cache']['max-age'], $query->getCacheMaxAge()),
        'contexts' => Cache::mergeContexts($build['#cache']['contexts'], $query->getCacheContexts()),
      ];
    }
  }

  /**
   * Returns the search API index configured for this field.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The loaded index entity.
   */
  protected function getSearchApiIndex(): IndexInterface {
    $index_id = $this->fieldDefinition->getSetting('index');
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);

    if (!$index) {
      throw new SearchApiException("Could not load index with ID '$index_id'.");
    }

    return $index;
  }

}
