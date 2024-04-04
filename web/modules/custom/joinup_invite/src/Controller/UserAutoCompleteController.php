<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class that handles an auto complete by first name, family name or email.
 */
class UserAutoCompleteController extends ControllerBase {

  /**
   * Returns auto completed user entries.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the auto complete suggestions.
   */
  public function userAutoComplete(Request $request): JsonResponse {
    $param = $request->query->get('q');
    $values = [];
    if ($param) {
      $userStorage = $this->entityTypeManager()->getStorage('user');
      $results = $userStorage->getQuery('OR')
        ->accessCheck(FALSE)
        ->condition('mail', $param, 'CONTAINS')
        ->condition('name', $param, 'CONTAINS')
        ->condition('field_user_first_name', $param, 'CONTAINS')
        ->condition('field_user_family_name', $param, 'CONTAINS')
        ->sort('field_user_first_name')
        ->sort('field_user_family_name')
        ->range(0, 10)
        ->execute();
      /** @var \Drupal\user\UserInterface[] $users */
      $users = $userStorage->loadMultiple($results);

      foreach ($users as $user) {
        $values[] = [
          'value' => $user->id(),
          'label' => $this->getAccountName($user),
        ];
      }
    }
    return new JsonResponse($values);
  }

  /**
   * Returns a full name of the user with their email as suffix in parentheses.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string version of user's full name.
   */
  protected function getAccountName(UserInterface $user): MarkupInterface {
    return $this->t('@name (@username)', [
      '@name' => $user->getDisplayName(),
      '@username' => $user->getAccountName(),
    ]);
  }

}
