<?php

namespace Drupal\joinup_user\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Site\Settings;
use Drupal\joinup_user\JoinupUserFullNameInterface;
use Drupal\system\Controller\EntityAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a route controller for user autocomplete form elements.
 *
 * This controller replaces the one provided by core for the user entity. Few
 * differences:
 *   - It shows the user full name instead of name. This is covering a security
 *     aspect as usernames are internals identifiers and should not be revealed
 *     publicly. The autocomplete suggestions are produced only by matching the
 *     strings from yhe user's full name, avoiding username enumeration.
 *   - Privileged users, granted with `administer users` permission, are able
 *     to match also by searching for strings included in the Drupal username,
 *     e-mail and EU Login username.
 */
class JoinupUserAutocompleteController extends EntityAutocompleteController {

  /**
   * Static cache for IDs of users sharing names with other users.
   */
  protected array $sharedNameUsers;

  public function __construct(
    EntityAutocompleteMatcherInterface $matcher,
    KeyValueStoreInterface $keyValue,
    protected readonly Connection $db,
  ) {
    parent::__construct($matcher, $keyValue);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity.autocomplete_matcher'),
      $container->get('keyvalue')->get('entity_autocomplete'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key): JsonResponse {
    if ($target_type !== 'user' || !in_array($selection_handler, ['default:user', 'default'], TRUE)) {
      return parent::handleAutocomplete($request, $target_type, $selection_handler, $selection_settings_key);
    }

    $matches = [];

    // Get the typed string from the URL, if it exists.
    $input = $request->query->get('q');

    // Check this string for emptiness, but allow any non-empty string.
    if (is_string($input) && strlen($input)) {
      $tagList = Tags::explode($input);
      $typedString = !empty($tagList) ? mb_strtolower(array_pop($tagList)) : '';
      $selectionSettings = $this->keyValue->get($selection_settings_key, FALSE);
      if ($selectionSettings !== FALSE) {
        $selectionSettingsHash = Crypt::hmacBase64(serialize($selectionSettings) . $target_type . $selection_handler, Settings::getHashSalt());
        if (!hash_equals($selectionSettingsHash, $selection_settings_key)) {
          // Settings hash does not match the passed-in key.
          throw new AccessDeniedHttpException('Invalid selection settings key.');
        }
      }
      else {
        // The selection settings key is not found in the key/value store.
        throw new AccessDeniedHttpException();
      }

      $matches = $this->getMatches($selectionSettings, $typedString);
    }

    return new JsonResponse($matches);
  }

  /**
   * Gets matched labels based on a given search string.
   *
   * @param array $selectionSettings
   *   An array of settings that will be passed to the selection handler.
   * @param string $typedString
   *   The user typed string.
   *
   * @return array
   *   An array of matched user entities, in the format required by the AJAX
   *   autocomplete API (e.g. [value' => $value, 'label' => $label]).
   */
  protected function getMatches(array $selectionSettings, string $typedString): array {
    $matches = [];

    if ($typedString === '') {
      return $matches;
    }

    $operator = !empty($selectionSettings['match_operator']) ? $selectionSettings['match_operator'] : 'CONTAINS';
    $limit = isset($selectionSettings['match_limit']) ? (int) $selectionSettings['match_limit'] : 10;
    $includeAnonymous = !empty($selectionSettings['include_anonymous']) ?? TRUE;

    $query = $this->db->select(JoinupUserFullNameInterface::TABLE, 'fullname')
      ->fields('fullname', ['uid', 'full_name'])
      ->orderBy('fullname.full_name')
      ->range(0, $limit);

    $typedStringEscaped = $this->db->escapeLike($typedString);
    $pattern = match ($operator) {
      'CONTAINS' => "%$typedStringEscaped%",
      'SHOW_ALL' => "$typedStringEscaped%",
    };
    $condition = $query->orConditionGroup()->condition('fullname.full_name', $pattern, 'LIKE');

    // Privileged users are able to search by sensitive information.
    if ($this->currentUser()->hasPermission('administer users')) {
      $query->leftJoin('users_field_data', 'user', '[fullname].[uid] = [user].[uid]');
      $query->leftJoin('authmap', 'cas', "[fullname].[uid] = [cas].[uid] AND [cas].[provider] = 'cas'");
      $query->fields('user', ['name']);

      // Suggestions from the {joinup_user_full_name} table should come first.
      $query->addExpression("IF([fullname].[full_name] LIKE :pattern, 0, 1)", 'sort_order', [
        ':pattern' => $pattern,
      ]);
      $query->orderBy('sort_order');

      $pattern = "%$typedStringEscaped%";
      $condition
        ->condition('user.name', $pattern, 'LIKE')
        ->condition('user.mail', $pattern, 'LIKE')
        ->condition('cas.authname', $pattern, 'LIKE');
    }

    $results = $query
      ->condition($condition)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if ($includeAnonymous) {
      $results[] = [
        'uid' => 0,
        'full_name' => $this->config('user.settings')->get('anonymous'),
      ];
      // Resort the array to place anonymous in its place.
      uasort($results, fn($a, $b) => SortArray::sortByKeyString($a, $b, 'full_name'));
      // Keep the $limit.
      array_pop($results);
    }

    foreach ($results as $row) {
      $row['full_name'] = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($row['full_name'])))));
      $matches[] = [
        'value' => "{$row['full_name']} ({$row['uid']})",
        'label' => $this->getLabel($row),
      ];
    }

    return $matches;
  }

  /**
   * @param array{uid: numeric-string|int, full_name: string, name: ?string} $row
   */
  protected function getLabel(array $row): string {
    if (!$row['uid']) {
      // Anonymous doesn't need to show the UID.
      return $row['full_name'];
    }

    if ($row['name']) {
      // Privileged user perspective: show also the username.
      return "{$row['full_name']} - {$row['name']} ({$row['uid']})";
    }

    $label = $row['full_name'];
    if ($this->hasSharedName($row['uid'])) {
      // The account shares the same full name with other account. We append the
      // user ID to allow the editor to pick up the correct one.
      $label .= " ({$row['uid']})";
    }

    return $label;
  }

  protected function hasSharedName(int|string $uid): bool {
    if (!isset($this->sharedNameUsers)) {
      // Retrieve all IDs of users sharing the name with other user.
      $sql = <<<QUERY
        SELECT GROUP_CONCAT([uid]), COUNT([full_name]) AS c
          FROM {joinup_user_full_name}
          GROUP BY [full_name]
          HAVING c > 1
        QUERY;
      foreach ($this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as [$uids]) {
        $this->sharedNameUsers += array_flip(explode(',', $uids));
      }
    }
    return isset($this->sharedNameUsers[$uid]);
  }

}
