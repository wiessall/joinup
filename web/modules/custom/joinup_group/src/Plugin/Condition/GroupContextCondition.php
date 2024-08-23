<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Condition;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a group context condition plugin.
 *
 * @Condition(
 *   id = "group_context",
 *   label = @Translation("Group context"),
 * )
 */
class GroupContextCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The group manager service.
   */
  protected JoinupGroupManagerInterface $groupManager;

  /**
   * Constructs a new group context condition plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $group_manager
   *   The group manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, JoinupGroupManagerInterface $group_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->groupManager = $group_manager;
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
      $container->get('joinup_group.group_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'group_context' => FALSE,
      'limit_to_groups' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['group_context'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group context is active'),
      '#default_value' => $this->getConfiguration()['group_context'],
    ];
    $form['limit_to_groups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Limit to groups'),
      '#default_value' => implode("\n", $this->getConfiguration()['limit_to_groups']),
      '#description' => $this->t('Enter one group ID per line.'),
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
      if ($invalid_ids = array_diff($ids, array_keys($groups))) {
        $form_state->setErrorByName('limit_to_groups', $this->t('The following group identifiers are invalid: @ids', [
          '@ids' => implode(', ', $invalid_ids),
        ]));
      }
    }
    $form_state->setValue('limit_to_groups', $ids);
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
    $this->setConfig('group_context', $group_context);
    $this->setConfig('limit_to_groups', $form_state->getValue('limit_to_groups'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): ?MarkupInterface {
    if (!$this->getConfiguration()['group_context']) {
      return NULL;
    }

    if ($groups = $this->getGroups()) {
      $group_labels = array_map(function (GroupInterface $group): string {
        return $group->get('rid')->entity->label() . ': ' . $group->label();
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
    if (!$this->getConfiguration()['group_context']) {
      // Cannot negate if the condition is not enabled.
      $this->setConfig('negate', FALSE);
      return FALSE;
    }

    if (!$group_from_context = $this->groupManager->getGroupFromContext()) {
      return FALSE;
    }

    $limit_to_groups = $this->getConfiguration()['limit_to_groups'];
    if (!$limit_to_groups) {
      return TRUE;
    }

    return in_array($group_from_context->id(), $limit_to_groups);
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
   *   (optional) A list of group IDs candidates.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   The list of groups this condition is limited to.
   */
  protected function getGroups(?array $ids = NULL): array {
    $ids ??= $this->getConfiguration()['limit_to_groups'];
    return array_filter(
      $ids ? $this->entityTypeManager->getStorage('node')->loadMultiple($ids) : [],
      function (NodeInterface $node): bool {
        return $node instanceof GroupInterface;
      }
    );
  }

  /**
   * Converts the list of IDs from text blob to an array.
   *
   * @param string $text
   *   The text blob.
   *
   * @return array
   *   A list of group IDs.
   */
  protected function textToArray(string $text): array {
    return array_values(
      array_filter(
        array_map('trim', explode("\n", str_replace("\r", "\n", $text)))
      )
    );
  }

}
