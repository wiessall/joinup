<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for autocomplete group filter.
 */
class GroupAutocompleteController extends ControllerBase {

  /**
   * Handles the autocomplete request and returns a JSON response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the autocomplete results.
   */
  public function handleAutocomplete(Request $request): JsonResponse {
    $results = [];
    $input = $request->query->get('q');

    if (empty($input)) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    $media_storage = $this->entityTypeManager()->getStorage('media');
    $query = $media_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', ['file', 'video'], 'IN')
      ->exists('og_audience');
    $media_ids = $query->execute();

    $media_entities = $media_storage->loadMultiple($media_ids);

    foreach ($media_entities as $media) {
      $group = $media->get('og_audience')->entity;
      if ($group instanceof GroupInterface) {
        $results[$group->id()] = [
          'value' => $group->id(),
          'label' => $group->label(),
        ];
      }
    }

    // Filter the results based on the input value.
    $filtered_results = array_filter($results, function ($item) use ($input) {
      return stripos($item['label'], $input) !== FALSE;
    });

    return new JsonResponse(array_values($filtered_results));
  }

}
