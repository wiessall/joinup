<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_core\ConfigReadOnlyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a developers warning message block.
 */
#[Block(
  id: "developer_warning_message",
  admin_label: new TranslatableMarkup("Developers warning message"),
  category: new TranslatableMarkup("Joinup"),
)]
final class WarningMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected ConfigReadOnlyInterface $fileConfigReadOnly
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
      $container->get('joinup_core.file_config_read_only')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $warnings = [
      $this->t('You are working on production. Please, be extra cautious!')
    ];

    if (!$this->fileConfigReadOnly->isReadOnlyEnabled()) {
      $warnings[] = $this->t('Config readonly is disabled!');
    }

    $build['content'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => $warnings,
      ],
    ];
    $build['#cache']['tags'][] = 'dashboard:read_only_config';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Allow the environment variable to not show this block on development
    // environment so that devs do not get used to the warning.
    $access = !getenv('IS_DDEV_PROJECT');
    return AccessResult::allowedIf($access);
  }

}
