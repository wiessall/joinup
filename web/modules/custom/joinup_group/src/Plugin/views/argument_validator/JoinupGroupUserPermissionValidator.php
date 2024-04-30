<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\og\PermissionManagerInterface;
use Drupal\user\PermissionHandlerInterface;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an argument validator plugin for groups.
 *
 * @ViewsArgumentValidator(
 *   id = "joinup_group_user_permission",
 *   title = @Translation("User permissions on group"),
 *   entity_type = "rdf_entity",
 * )
 */
class JoinupGroupUserPermissionValidator extends ArgumentValidatorPluginBase {

  /**
   * The OG permission manager service.
   */
  protected PermissionManagerInterface $ogPermissionManager;

  /**
   * The permission handler service.
   */
  protected PermissionHandlerInterface $permissionHandler;

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The Joinup Group manager service.
   */
  protected JoinupGroupManagerInterface $groupManager;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new argument validator plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\PermissionManagerInterface $og_permission_manager
   *   The OG permission manager service.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $group_manager
   *   The Joinup Group manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PermissionManagerInterface $og_permission_manager, PermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler, JoinupGroupManagerInterface $group_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ogPermissionManager = $og_permission_manager;
    $this->permissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
    $this->groupManager = $group_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.permission_manager'),
      $container->get('user.permissions'),
      $container->get('module_handler'),
      $container->get('joinup_group.group_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    return [
      'group_permission' => ['default' => NULL],
      'site_permission' => ['default' => NULL],
    ] + parent::defineOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $group_options = [];
    $display_name = (string) $this->t('Group specific permissions');
    foreach (JoinupGroupHelper::GROUP_BUNDLES as $bundle) {
      $permissions = $this->ogPermissionManager->getDefaultGroupPermissions('rdf_entity', $bundle);
      foreach ($permissions as $permission => $info) {
        $group_options[$display_name][$permission] = $info->getTitle();
      }
    }

    $site_wide_options = [];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $permission => $info) {
      $provider = $info['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $site_wide_options[$display_name][$permission] = strip_tags((string) $info['title']);
    }

    $form['group_permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Select group permission'),
      '#options' => $group_options + $site_wide_options,
      '#default_value' => $this->options['group_permission'],
    ];
    $form['validate_site_permission'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("If group permission doesn't validate, check also the site permission..."),
      '#default_value' => (bool) $this->options['site_permission'],
    ];
    $form['site_permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Select site permission'),
      '#title_display' => 'invisible',
      '#options' => $site_wide_options,
      '#default_value' => $this->options['site_permission'],
      '#states' => [
        'visible' => [
          ':input[name="options[validate][options][' . $this->getPluginId() . '][validate_site_permission]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state, &$options = []): void {
    parent::submitOptionsForm($form, $form_state, $options);
    if (!$options['validate_site_permission']) {
      $options['site_permission'] = NULL;
    }
    unset($options['validate_site_permission']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg): bool {
    if ($arg) {
      $group = $this->entityTypeManager->getStorage('rdf_entity')->load($arg);
      if ($group instanceof GroupInterface) {
        return $this->groupManager
          ->access($group, $this->options['group_permission'], $this->options['site_permission'])
          ->isAllowed();
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition(): EntityContextDefinition {
    return EntityContextDefinition::fromEntityTypeId('rdf_entity')
      ->setLabel($this->argument->adminLabel())
      ->setRequired(FALSE);
  }

}
