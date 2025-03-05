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
use Drupal\node\NodeInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      '#description' => $this->t('Enter one group URI per line.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $uris = $this->textToArray($form_state->getValue('limit_to_groups'));
    if ($uris) {
      $groups = $this->getGroups($uris);
      if ($invalid_uris = array_diff($uris, array_keys($groups))) {
        $form_state->setErrorByName('limit_to_groups', $this->t('The following group URIs are invalid: @uris', [
          '@uris' => implode(', ', $invalid_uris),
        ]));
      }
    }
    $form_state->setValue('limit_to_groups', $uris);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!$group_context = (bool) $form_state->getValue('group_context')) {
      // Cannot negate if the condition is not enabled.
      $this->setConfig('negate', FALSE);
    }
    $this->setConfig('limit_to_groups', $form_state->getValue('limit_to_groups'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): ?MarkupInterface {
    if ($groups = $this->getGroups()) {
      $group_labels = array_map(function (GroupInterface $group): string {
        return $group->get('type')->entity->label() . ': ' . $group->label();
      }, $groups);
      $args = ['@groups' => implode(', ', $group_labels)];
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
    if (!$group_from_context = $this->groupManager->getGroupFromContext()) {
      return FALSE;
    }

    $limit_to_groups = $this->getConfiguration()['limit_to_groups'];
    if (!$limit_to_groups) {
      return TRUE;
    }

    return in_array($group_from_context->getUri(), $limit_to_groups);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = Cache::mergeTags(parent::getCacheTags(), [
      'node_list:collection',
      'node_list:solution',
    ]);
    foreach ($this->getGroups() as $group) {
      $tags = Cache::mergeTags($tags, $group->getCacheTagsToInvalidate());
    }
    return $tags;
  }

  /**
   * Returns a list of groups this condition is limited to.
   *
   * @param array|null $ids
   *   (optional) A list of group URIs candidates.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   The list of groups this condition is limited to.
   */
  protected function getGroups(?array $ids = NULL): array {
    $ids ??= $this->getConfiguration()['limit_to_groups'];
    $groups = [];
    foreach ($ids as $id) {
      if (is_numeric($id)) {
        $groups[$id] = $this->entityTypeManager->getStorage('node')->load($id);
      }
      elseif ($entity = $this->mapper->getEntityByUri($id)) {
        // Try to load the group by URI for backwards compatibility.
        $groups[$entity->id()] = $this->mapper->getEntityByUri($id);
      }
      // Further attempt to recognize the group from the URL if the ID is
      // a URI and is internal.
      elseif (!UrlHelper::isExternal($id) || UrlHelper::externalIsLocal($id, \Drupal::request()->getSchemeAndHttpHost())) {
        $url = Url::fromUri('internal:' . UrlHelper::parse($id)['path']);
        if ($url->isRouted() && $url->getRouteName() === 'entity.node.canonical') {
          $node = $this->entityTypeManager->getStorage('node')->load($url->getRouteParameters()['node']);
          if ($node instanceof GroupInterface) {
            $groups[$node->id()] = $node;
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
