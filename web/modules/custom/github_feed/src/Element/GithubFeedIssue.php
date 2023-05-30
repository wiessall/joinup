<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element to display a Github issue.
 *
 * Properties:
 * - #url: The URL of the repository.
 * - #repository_url: The URL of the repository.
 * - #number: The issue number.
 * - #title: The issue title.
 * - #state: The issue state.
 * - #created_at: The issue creation date.
 * - #updated_at: The issue last update date.
 * - #body: The issue body.
 *
 * Usage Example:
 * @code
 * $build['node'] = [
 *   '#type' => 'github_issue',
 *   '#url' => 'http://github.com/drupal/github_feed/issues/1',
 *   '#repository_url' => 'http://github.com/drupal/github_feed',
 *   '#number' => 1,
 *   '#title' => 'Issue title',
 *   '#state' => 'open',
 *   '#created_at' => '2017-01-01T00:00:00Z',
 *   '#updated_at' => '2017-01-01T00:00:00Z',
 *   '#body' => 'Issue body',
 * ];
 * @endcode
 *
 * @RenderElement("github_issue")
 */
class GithubFeedIssue extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [get_class($this), 'preRenderElement'],
      ],
      '#url' => NULL,
      '#repository_url' => NULL,
      '#number' => NULL,
      '#title' => NULL,
      '#state' => NULL,
      '#created_at' => NULL,
      '#updated_at' => NULL,
      '#body' => NULL,
    ];
  }

  /**
   * Entity element pre render callback.
   *
   * @param array $element
   *   An associative array containing the properties of the entity element.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderElement(array $element) {
    $element['#theme'] = 'github_issue';
    return $element;
  }

}
