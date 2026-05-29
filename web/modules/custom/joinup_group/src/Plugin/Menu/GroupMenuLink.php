<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\joinup_group\Entity\GroupMenuLinkInterface;

/**
 * Defines a menu link for a group.
 *
 * This is heavily inspired by the 'menu_link_content' plugin, but instead of
 * deriving over the menu link content entities, it derives over entities whose
 * bundle class are implementing the GroupMenuLinkInterface interface, such as
 * custom pages.
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
   * Static cache of the entity associated with this menu link, if any.
   */
  protected GroupMenuLinkInterface $entity;

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
    if ($this->isGroupMenuLinkEntityBased()) {
      return $this->getEntity()->label();
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
    if ($persist && $this->isGroupMenuLinkEntityBased()) {
      [, $ogMenuType] = explode('-', $this->getDerivativeId());

      $settings = $this->getEntity()->get('menu_settings')->value;
      $settings[$ogMenuType] = [
        'weight' => $this->getWeight(),
        'enabled' => $this->isEnabled(),
        'parent' => $this->getParent(),
      ];
      $this->getEntity()->setInsidePlugin()->set('menu_settings', ['value' => $settings])->save();
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
  protected function getEntity(): GroupMenuLinkInterface {
    if (!isset($this->entity)) {
      $storage = $this->entityTypeManager->getStorage('node');
      [$nid] = explode('-', $this->getDerivativeId(), 2);
      $entity = $storage->load($nid);

      if (!$entity instanceof GroupMenuLinkInterface) {
        throw new PluginException("Entity with NID $nid doesn't exist.");
      }

      // Clone the entity object to avoid tampering with the static cache.
      $this->entity = clone $entity;
      $translation = $this->entityRepository->getTranslationFromContext($this->entity);
      assert($translation instanceof GroupMenuLinkInterface);
      $this->entity = $translation;
    }

    return $this->entity;
  }

  /**
   * Provides a convenient way to generate a group menu link plugin ID.
   *
   * @param int|string $id
   *   The ID.
   * @param string $ogMenuType
   *   The OG menu instance type.
   *
   * @return string
   *   The plugin ID.
   */
  public static function buildPluginId(int|string $id, string $ogMenuType): string {
    return self::BASE_ID . ':' . $id . '-' . $ogMenuType;
  }

  /**
   * Extracts the ID from a group menu link plugin ID.
   *
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return string|null
   *   The ID, or NULL if the plugin is not a group menu link.
   */
  public static function extractId(string $pluginId): ?string {
    if (str_starts_with($pluginId, self::BASE_ID . ':')) {
      [, $derivativeId] = explode(':', $pluginId, 2);
      [$id] = explode('-', $derivativeId, 2);
      return $id;
    }
    return NULL;
  }

  /**
   * Determines whether this menu link is based on an entity.
   *
   * Entity-based menu links are those that are generated one for each entity
   * implementing GroupMenuLinkInterface interface.
   *
   * @return bool
   *   TRUE if the menu link is based on an entity.
   */
  protected function isGroupMenuLinkEntityBased(): bool {
    [$id] = explode('-', $this->getDerivativeId(), 2);
    return is_numeric($id);
  }

}
