<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\area;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views area handler for assessments reports.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("assessments_reports_links")]
class AssessmentsReportsLinks extends AreaPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
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
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $links = $this->getLinks();
    if (empty($links)) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['assessments-report-links', 'mt-5', 'pb-3']],
    ];

    foreach ($links as $link) {
      $build[] = [
        '#type' => 'link',
        '#url' => $link['url'],
        '#title' => $link['title'],
        '#attributes' => [
          'class' => [
            'btn',
            'btn-primary',
            'me-2',
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Returns links to display.
   */
  protected function getLinks(): array {
    $links = [
      [
        'url' => Url::fromRoute('entity.assessment.add_form', ['assessment_type' => 'report']),
        'title' => $this->t('New assessment'),
      ],
      [
        'url' => Url::fromRoute('view.assessments_reports.user_reports'),
        'title' => $this->t('My assessments'),
      ],
    ];

    return \array_filter($links, function (array $link) {
      $url = $link['url'];
      \assert($url instanceof Url);

      // Skip if the link points to the current route.
      if ($url->isRouted() && $this->routeMatch->getRouteName() === $url->getRouteName()) {
        return FALSE;
      }

      // Check URL access.
      return $url->access();
    });
  }

}
