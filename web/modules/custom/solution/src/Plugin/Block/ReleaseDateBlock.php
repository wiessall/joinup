<?php

declare(strict_types=1);

namespace Drupal\solution\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block showing the release date.
 *
 * @todo This should probably move to joinup_release.
 */
#[Block(
  id: 'solution_release_date',
  admin_label: new TranslatableMarkup('Release date'),
  category: new TranslatableMarkup('Solution'),
)]
class ReleaseDateBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a release date.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date/time formatter service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected RouteMatchInterface $routeMatch,
    protected DateFormatterInterface $dateFormatter,
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
      $container->get('current_route_match'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $release = $this->getLatestRelease();
    if (!$release) {
      return [];
    }

    $output = $release->get('created')->view([
      'type' => 'timestamp',
      'label' => 'above',
      'settings' => [
        'date_format' => 'date_only',
      ],
    ]);

    $output['#title'] = $this->t('Release date');
    return $output;
  }

  /**
   * Gets the latest release.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface|null
   *   The latest release.
   */
  protected function getLatestRelease(): ?ReleaseInterface {
    $entity = $this->routeMatch->getParameter('node');

    if ($entity instanceof SolutionInterface) {
      return $entity->getLatestRelease();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf((bool) $this->getLatestRelease());
  }

}
