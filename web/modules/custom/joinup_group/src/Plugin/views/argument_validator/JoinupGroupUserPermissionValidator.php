<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
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
 *   entity_type = "node",
 * )
 */
class JoinupGroupUserPermissionValidator extends ArgumentValidatorPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PermissionManagerInterface $ogPermissionManager,
    protected PermissionHandlerInterface $permissionHandler,
    protected ModuleExtensionList $moduleExtensionList,
    protected JoinupGroupManagerInterface $groupManager,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('og.permission_manager'),
      $container->get('user.permissions'),
      $container->get('extension.list.module'),
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
      $permissions = $this->ogPermissionManager->getDefaultGroupPermissions('node', $bundle);
      foreach ($permissions as $permission => $info) {
        $group_options[$display_name][$permission] = $info->getTitle();
      }
    }

    $site_wide_options = [];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $permission => $info) {
      $provider = $info['provider'];
      $display_name = $this->moduleExtensionList->getName($provider);
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
      $group = $this->entityTypeManager->getStorage('node')->load($arg);
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
    return EntityContextDefinition::fromEntityTypeId('node')
      ->setLabel($this->argument->adminLabel())
      ->setRequired(FALSE);
  }

}
