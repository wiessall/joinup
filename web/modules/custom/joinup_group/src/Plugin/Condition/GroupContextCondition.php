<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Condition;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a group context condition plugin.
 */
#[Condition(
  id: "group_context",
  label: new TranslatableMarkup("Group context"),
)]
class GroupContextCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RequestStack $requestStack,
    protected readonly JoinupGroupManagerInterface $groupManager,
    protected readonly RdfSyncMapper $mapper,
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
      $container->get('request_stack'),
      $container->get('joinup_group.group_manager'),
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'limit_to_groups' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['limit_to_groups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Limit to groups'),
      '#default_value' => implode("\n", $this->getConfiguration()['limit_to_groups']),
      '#description' => $this->t('Enter one group entry per line. The entry can be either the Node ID, the RDF sync URI, or the internal path of the group. <strong>Note that the URIs will be converted to the entity ID on save.</strong>'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $ids = $this->textToArray($form_state->getValue('limit_to_groups'));
    if ($ids) {
      $groups = $this->getGroups($ids);
      // Get the keys that have a null target entity.
      $invalidIds = array_diff(array_keys($groups), array_keys(array_filter($groups)));
      if ($invalidIds) {
        $form_state->setErrorByName('limit_to_groups', $this->t('The following group URIs are invalid: @ids', [
          '@ids' => implode(', ', $invalidIds),
        ]));
      }
    }

    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // At this point, all groups are valid and filtered.
    $limit_to_groups = $form_state->getValue('limit_to_groups') ?? '';
    $groups = $this->getGroups($this->textToArray($limit_to_groups));
    $limit_to_groups = array_map(fn (GroupInterface $group): string => $group->id(), $groups);
    $this->setConfig('limit_to_groups', array_unique($limit_to_groups));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): ?MarkupInterface {
    $groups = $this->getGroups();

    if (!empty($groups)) {
      $groupLabels = array_map(function (GroupInterface|null $group): string {
        return $group ? $group->get('type')->entity->label() . ': ' . $group->label() : 'Invalid';
      }, $groups);
      $args = ['@groups' => implode(', ', $groupLabels)];
      if (!$this->isNegated()) {
        return $this->t('Return true when any of the following groups context is active: @groups', $args);
      }
      else {
        return $this->t('Return false when any of the following groups context is active: @groups', $args);
      }
    }

    if (!$this->isNegated()) {
      return $this->t('Return true when any group context is active');
    }
    return $this->t('Return false when any group context is active');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (!$groupFromContext = $this->groupManager->getGroupFromContext()) {
      return FALSE;
    }

    $limitToGroups = $this->getConfiguration()['limit_to_groups'];
    if (!$limitToGroups) {
      return TRUE;
    }

    // Negate condition is calculated automatically on return.
    return in_array($groupFromContext->id(), $limitToGroups);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = Cache::mergeTags(parent::getCacheTags(), [
      'node_list:collection',
      'node_list:solution',
    ]);

    $groups = $this->getGroups();
    foreach ($groups as $group) {
      $tags = Cache::mergeTags($tags, $group->getCacheTagsToInvalidate());
    }
    return $tags;
  }

  /**
   * Returns a list of groups this condition is limited to.
   *
   * @param array|null $data
   *   (optional) A list of group URI, URL or Id candidates.
   *
   * @return array
   *   Returns both the list of groups and the list of invalid IDs.
   */
  protected function getGroups(?array $data = NULL): array {
    $data ??= $this->getConfiguration()['limit_to_groups'];
    $groups = [];
    foreach ($data as $id) {
      $groups[$id] = NULL;
      if (is_numeric($id)) {
        $groups[$id] = $this->entityTypeManager->getStorage('node')->load($id);
      }
      // Try to load the group by URI for backwards compatibility or URL for
      // convenience.
      elseif ($entity = $this->mapper->getEntityByUri($id)) {
        $groups[$id] = $entity;
      }
      // Further attempt to recognize the group from the URL if the ID is
      // a URI and is internal.
      elseif (!UrlHelper::isExternal($id) || UrlHelper::externalIsLocal($id, $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost())) {
        $path = trim(UrlHelper::parse($id)['path'], '/');
        $url = Url::fromUri("internal:/" . $path);
        if ($url->isRouted() && $url->getRouteName() === 'entity.node.canonical') {
          $node = $this->entityTypeManager->getStorage('node')->load($url->getRouteParameters()['node']);
          if ($node instanceof GroupInterface) {
            $groups[$id] = $node;
          }
        }
      }
    }

    return $groups;
  }

  /**
   * Converts the list of URIs from text blob to an array.
   *
   * @param string $text
   *   The text blob.
   *
   * @return array
   *   A list of group URIs.
   */
  protected function textToArray(string $text): array {
    return array_values(
      array_filter(
        array_map('trim', explode("\n", str_replace("\r", "\n", $text)))
      )
    );
  }

}
