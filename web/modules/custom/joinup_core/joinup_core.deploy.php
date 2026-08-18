<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Component\Utility\UrlHelper;
use Drupal\joinup_core\Update\RevisionTranslationAffectedRepair;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Converts absolute internal menu link URLs into internal paths.
 */
function joinup_core_deploy_201600(array &$sandbox): string {
  if (!isset($sandbox['uris'])) {
    $query = \Drupal::database()->select('menu_link_content_data', 'md');
    $query->fields('md', ['id', 'link__uri']);
    // The 'link' field is not translatable, so the default translation carries
    // the only value that needs to be inspected.
    $query->condition('md.default_langcode', 1);
    $query->condition('md.link__uri', 'http%', 'LIKE');

    // The converted URI is computed once, here, and kept for the processing
    // phase, keyed by menu link ID. Links that need no conversion are dropped
    // straight away rather than being loaded and inspected a second time.
    $sandbox['uris'] = [];
    foreach ($query->execute() as $row) {
      $internalUri = _joinup_core_get_internal_uri($row->link__uri);
      if ($internalUri !== NULL) {
        $sandbox['uris'][(int) $row->id] = $internalUri;
      }
    }
    $sandbox['total'] = count($sandbox['uris']);
    $sandbox['progress'] = 0;
  }

  if (!$sandbox['uris']) {
    $sandbox['#finished'] = 1;
    return 'No menu links with an absolute internal URL were found.';
  }

  // Taking the key and unsetting it avoids the array_shift() reindexing of the
  // whole remaining list on every iteration.
  $id = array_key_first($sandbox['uris']);
  $internalUri = $sandbox['uris'][$id];
  unset($sandbox['uris'][$id]);
  $sandbox['progress']++;
  $sandbox['#finished'] = (int) empty($sandbox['uris']);

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface|null $link */
  $link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->load($id);

  if (!$link instanceof MenuLinkContentInterface) {
    return sprintf(
      'Skipped menu link %d, it no longer exists, %d out of %d.',
      $id,
      $sandbox['progress'],
      $sandbox['total']
    );
  }

  $uri = $link->link->uri;
  $link->link->uri = $internalUri;
  // Let's not create unnecessary revisions.
  $link->setNewRevision(FALSE);
  $link->save();

  return sprintf(
    "Converted menu link %d from '%s' to '%s', %d out of %d.",
    $id,
    $uri,
    $internalUri,
    $sandbox['progress'],
    $sandbox['total']
  );
}

/**
 * Returns the internal URI equivalent of an absolute URL pointing at Joinup.
 *
 * This is mainly a copy of
 * \Drupal\menu_link_content\Form\MenuLinkContentForm::reformatInternalLinkIfEnteredAsExternal
 * from the patch.
 *
 * @param string $uri
 *   The URI stored in the menu link.
 *
 * @return string|null
 *   The equivalent 'internal:' URI, or NULL if the URI is not an absolute URL
 *   pointing at Joinup.
 */
function _joinup_core_get_internal_uri(string $uri): ?string {
  $request = \Drupal::request();
  $hosts = array_map('strtolower', [
    $request->getHost(),
    'interoperable-europe.ec.europa.eu',
    'joinup.ec.europa.eu',
  ]);

  // Schemes such as mailto: and tel: are external but carry no host.
  if (!UrlHelper::isExternal($uri)) {
    return NULL;
  }
  $parts = parse_url($uri);
  if (empty($parts['host']) || !in_array(strtolower($parts['host']), $hosts, TRUE)) {
    return NULL;
  }

  $path = $parts['path'] ?? '/';
  // Drop the base path, in case the portal is served from a subdirectory.
  $basePath = $request->getBasePath();
  if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
    $path = substr($path, strlen($basePath));
  }

  // Drop the language prefix, if any.
  $prefixes = array_filter(\Drupal::config('language.negotiation')->get('url.prefixes') ?? []);
  $segments = explode('/', ltrim($path, '/'));
  if (in_array($segments[0], $prefixes, TRUE)) {
    array_shift($segments);
  }

  return 'internal:/' . implode('/', $segments)
    . (isset($parts['query']) ? '?' . $parts['query'] : '')
    . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

/**
 * Clears revision_translation_affected where no translation was affected.
 */
function joinup_core_deploy_201601(array &$sandbox): string {
  return \Drupal::classResolver(RevisionTranslationAffectedRepair::class)->run($sandbox);
}
