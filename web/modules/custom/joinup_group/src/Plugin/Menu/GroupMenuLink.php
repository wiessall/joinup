<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\GroupMenuLinkInterface;

/**
 * Defines a menu link for a group.
 *
 * This is heavily inspired by the 'menu_link_content' plugin, but instead of
 * deriving over the menu link content entities, it derives over nodes whose
 * bundle class are implementing the GroupMenuLinkInterface interface, such as
 * the custom pages.
 *
 * Plugin ID pattern: group_menu_link:{link_type}-{identifier}-{og_menu_bundle}
 * - link_type: There are 2 types of links: 'node' for menu links derived over
 *   nodes. A typical example is the group custom page. A link is generated for
 *   each custom page. The other types are link generated one per group, such as
 *   'overview', 'glossary', etc.
 * - identifier: The identifier of the node. For the 'node' link type it's the
 *   ID of the node where the link points to. For the other types, it's the
 *   group node ID.
 * - og_menu_bundle: The OG menu bundle where the link is placed.
 */
final class GroupMenuLink extends MenuLinkBase implements ContainerFactoryPluginInterface {

  public const string BASE_ID = 'group_menu_link';

  /**
   * {@inheritdoc}
   */
  protected $overrideAllowed = [
    'menu_name' => TRUE,
    'title' => TRUE,
    'weight' => TRUE,
    'parent' => TRUE,
    'enabled' => TRUE,
  ];

  /**
   * Static cache of the node associated with this menu link, if any.
   */
  protected GroupMenuLinkInterface $node;

  /**
   * Static cache of the group overview link title.
   */
  protected string $overviewLabel;

  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    $type = $this->getGroupMenuLinkType();
    if ($type === 'node') {
      return $this->getNode()->label();
    }
    elseif ($type === 'overview') {
      return $this->getOverviewLabel();
    }
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist): array {
    // Filter the list of updates to only those that are allowed.
    $overrides = array_intersect_key($new_definition_values, $this->overrideAllowed);
    // Update the definition.
    $this->pluginDefinition = $overrides + $this->getPluginDefinition();

    $type = $this->getGroupMenuLinkType();
    if ($persist && $type === 'node') {
      [,, $ogMenuType] = explode('-', $this->getDerivativeId());

      $settings = $this->getNode()->get('menu_settings')->value;
      $settings[$ogMenuType] = [
        'weight' => $this->getWeight(),
        'enabled' => $this->isEnabled(),
        'parent' => $this->getParent(),
      ];
      $this->getNode()
        ->setInsidePlugin()
        ->set('menu_settings', ['value' => $settings])
        ->save();
    }

    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink(): void {}

  /**
   * Returns the entity associated with this menu link.
   *
   * @return \Drupal\joinup_group\Entity\GroupMenuLinkInterface
   *   The entity that makes up this menu link.
   */
  protected function getNode(): GroupMenuLinkInterface {
    if (!isset($this->node)) {
      $storage = $this->entityTypeManager->getStorage('node');
      [, $nid] = explode('-', $this->getDerivativeId(), 3);
      $node = $storage->load($nid);

      if (!$node instanceof GroupMenuLinkInterface) {
        throw new PluginException("Node with NID $nid doesn't exist.");
      }

      // Clone the node object to avoid tampering with the static cache.
      $this->node = clone $node;
      $translation = $this->entityRepository->getTranslationFromContext($this->node);
      assert($translation instanceof GroupMenuLinkInterface);
      $this->node = $translation;
    }

    return $this->node;
  }

  /**
   * Provides a convenient way to generate a group menu link plugin ID.
   *
   * @param string $type
   *   The type of the group menu link.
   * @param int|string $nid
   *   The node ID. It the node is either a group entity ID or a node
   *   implementing the GroupMenuLinkInterface interface.
   * @param string $ogMenuType
   *   The OG menu instance type.
   *
   * @return string
   *   The plugin ID.
   *
   * @throws \InvalidArgumentException
   *   If the type is invalid.
   */
  public static function buildPluginId(string $type, int|string $nid, string $ogMenuType): string {
    return self::BASE_ID . ':' . $type . '-' . $nid . '-' . $ogMenuType;
  }

  /**
   * Extracts the node ID from a group menu link plugin ID.
   *
   * This is the ID of the entity that is deriving the plugin. It's a node ID
   * for plugins implementing GroupMenuLinkInterface, such as custom pages; and
   * a group ID for plugins derived by the group entity, such as 'overview',
   * 'glossary', etc.
   *
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return string|null
   *   The ID, or NULL if the plugin is not a group menu link.
   */
  public static function extractNodeId(string $pluginId): ?string {
    if (str_starts_with($pluginId, self::BASE_ID . ':')) {
      [, $derivativeId] = explode(':', $pluginId, 2);
      [, $nid] = explode('-', $derivativeId, 3);
      return $nid;
    }
    return NULL;
  }

  /**
   * Returns the type of the menu link.
   *
   * @return string
   *   The link type:
   *   - 'node' for a node implementing the GroupMenuLinkInterface interface.
   *   - a string such as 'overview', 'glossary', etc. for a group derived link.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the type is invalid.
   */
  protected function getGroupMenuLinkType(): string {
    return explode('-', $this->getDerivativeId(), 3)[0];
  }

  /**
   * Returns the label of the group overview page.
   */
  public function getOverviewLabel(): string {
    if (!isset($this->overviewLabel)) {
      $groupId = self::extractNodeId($this->getPluginId());
      $group = $this->entityTypeManager->getStorage('node')->load($groupId);
      if (!$group instanceof GroupInterface) {
        throw new \LogicException("Node ID $groupId should refer a group");
      }
      $this->overviewLabel = $group->getSetting('overview_label')->value ?? $this->pluginDefinition['title'];
    }

    return $this->overviewLabel;
  }

}
