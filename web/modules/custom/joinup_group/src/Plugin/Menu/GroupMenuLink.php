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
  protected $overrideAllowed = ['weight', 'parent', 'enabled'];

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
    return $this->getEntity()->label();
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
    $overrides = array_intersect_key($new_definition_values, array_flip($this->overrideAllowed));
    // Update the definition.
    $this->pluginDefinition = $overrides + $this->getPluginDefinition();
    if ($persist) {
      [$ogmenuInstanceId] = explode('-', $this->getDerivativeId());

      $settings = $this->getEntity()->get('menu_settings')->value;
      $settings[$ogmenuInstanceId] = [
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
    return $this->getEntity()->isTranslatable();
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
      [, $nid] = explode('-', $this->getDerivativeId());
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
   * @param string $derivative
   *   The plugin derivative ID.
   *
   * @return string
   *   The plugin ID.
   */
  public static function id(string $derivative): string {
    return self::BASE_ID . ':' . $derivative;
  }

}
