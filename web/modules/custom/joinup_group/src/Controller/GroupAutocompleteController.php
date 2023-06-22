<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for autocomplete group filter.
 */
class GroupAutocompleteController extends ControllerBase {

  /**
   * @return JsonResponse
   */
  public function handleAutocomplete(Request $request)
  {

    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);

    $media_storage = $this->entityTypeManager()->getStorage('media');
    $mids = $media_storage->getQuery()->accessCheck(FALSE)
      ->condition('bundle', ['file', 'video'], 'IN')
      ->exists('og_audience')
      ->execute();
    $groups = array_map(function (MediaInterface $media): GroupInterface {
      return $media->get('og_audience')->entity;
    }, $media_storage->loadMultiple($mids));


    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    foreach ($groups as $group) {

      $results[] = [
        'value' => $group->id(),
        'label' => t('@title (@type)', [
          '@type' => $group->get('rid')->entity->label(),
          '@title' => $group->label(),
        ])
      ];
    }
    $results = array_values(array_column($results, null, 'value'));

    // Filter the array based on the label value
    $results = array_filter($results, function ($item) use ($input) {
      return stripos($item['label']->render(), $input) !== false;
    });
    $results = array_values($results);

    return new JsonResponse($results);
  }
}
