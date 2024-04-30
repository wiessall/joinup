<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\user\PermissionHandlerInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides permission-based access control.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "og_permission",
 *   title = @Translation("OG permission"),
 *   help = @Translation("Access will be granted to users with the specified
 *   permission string in grouop or site wide.")
 * )
 */
class OgPermission extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * Constructs Og permission object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\user\PermissionHandlerInterface $permissionHandler
   *   The permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   The Joinup Group manager service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected PermissionHandlerInterface $permissionHandler,
    protected ModuleHandlerInterface $moduleHandler,
    protected JoinupGroupManagerInterface $groupManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.permissions'),
      $container->get('module_handler'),
      $container->get('joinup_group.group_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    $group = $this->groupManager->getGroupFromContext();
    $permission = $this->options['og_permission'];

    return $group && $this->groupManager->access($group, $permission, $permission, $account)
      ->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    $route->setRequirement('_og_permission', $this->options['og_permission']);
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle(): TranslatableMarkup {
    $permissions = $this->permissionHandler->getPermissions();
    if (isset($permissions[$this->options['og_permission']])) {
      return $permissions[$this->options['og_permission']]['title'];
    }

    return $this->t('Permission: @perm', ['@perm' => $this->options['og_permission']]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $options = [];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $perm => $permItem) {
      $provider = $permItem['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $options[$display_name][$perm] = $permItem['title'];
    }

    $form['og_permission'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Permission'),
      '#default_value' => $this->options['og_permission'],
      '#description' => $this->t('Only users with the selected permission flag will be able to access this display.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [
      'user.permissions',
      'og_group_context',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['og_permission'] = ['default' => 'access content'];

    return $options;
  }

}
