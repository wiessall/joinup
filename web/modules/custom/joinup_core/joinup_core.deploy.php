<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Component\Utility\UrlHelper;
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
 * Repair revisions data after ISAICP-11533.
 */
function joinup_core_deploy_201601(array &$sandbox): string {
  $db = \Drupal::database();

  if (!isset($sandbox['data'])) {
    $sql = <<<SQL
      SELECT
        nfr.nid,
        nfr.vid,
        -- Will create values such as 'fr:1,de:,ro:1' indicating which language
        -- is affected. The empty value indicates NULL.
        GROUP_CONCAT(
            CONCAT(
                nfr.langcode,
                ':',
                COALESCE(nfr.revision_translation_affected, '')
            ) ORDER BY nfr.langcode
        ) AS langcodes
      FROM {node_field_revision} nfr
      INNER JOIN {node_revision} nr ON nfr.vid = nr.vid
      INNER JOIN {node__og_audience} og ON nfr.nid = og.entity_id
      WHERE
        -- Only consider nodes created by TMGMT.
        nr.revision_log LIKE 'Created by translation job <a href="/admin/tmgmt/jobs/%'
        -- Don't include OSS catalogue nodes, they have a different translation.
        AND og.og_audience_target_id <> 723282
      GROUP BY nfr.nid, nfr.vid
      ORDER BY nfr.nid, nfr.vid
      SQL;
    $rows = $db->query($sql)->fetchAll();

    // Convert to a structured array and normalize data.
    $sandbox['data'] = [];
    foreach ($rows as $row) {
      $affected = [];
      foreach (array_filter(explode(',', (string) $row->langcodes)) as $item) {
        [$langcode, $value] = explode(':', $item, 2);
        $affected[$langcode] = $value === '' ? NULL : intval($value);
      }
      foreach ($affected as $langcode => $value) {
        $sandbox['data'][$row->nid][$row->vid][$langcode] = $value;
      }
    }

    // Compute the correct values and remove unchanged revisions from the set.
    foreach ($sandbox['data'] as $nid => &$item) {
      $progress = [];

      foreach ($item as $vid => &$affected) {
        $changed = FALSE;

        foreach ($affected as $langcode => &$value) {
          if ($value === 1) {
            // Mark the 1st non-English `revision_translation_affected` which is
            // 1 (TRUE). We keep that as the correct value for this revision.
            if (!isset($progress[$langcode]) && $langcode !== 'en') {
              $progress[$langcode] = TRUE;
              continue;
            }

            // There's already a translation revision affected for this node,
            // mark all others, wrongly set to 1, as NULL.
            $value = NULL;
            $changed = TRUE;
            continue;
          }

          // This revision has a correct translation_affected value. Delete it
          // from the set to avoid useless update queries.
          unset($affected[$langcode]);
        }

        // Remove node revisions correctly created.
        if (!$changed || empty($affected)) {
          unset($sandbox['data'][$nid][$vid]);
        }
      }
    }
    // Remove empty entries.
    $sandbox['data'] = array_filter($sandbox['data']);

    $sandbox['total'] = count($sandbox['data']);
    $sandbox['progress'] = 0;
  }

  $data = array_slice($sandbox['data'], 0, 100, TRUE);
  $sandbox['data'] = array_diff_key($sandbox['data'], $data);
  $sandbox['progress'] += count($data);

  $sql = <<<SQL
      UPDATE {node_field_revision}
      SET revision_translation_affected = :value
      WHERE nid = :nid AND vid = :vid AND langcode = :langcode
      SQL;

  foreach ($data as $nid => $revisions) {
    foreach ($revisions as $vid => $affected) {
      foreach ($affected as $langcode => $affectedValue) {
        $db->query($sql, [
          ':nid' => $nid,
          ':vid' => $vid,
          ':langcode' => $langcode,
          ':value' => $affectedValue,
        ]);
      }
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);

  return "Repaired revisions for $sandbox[progress] out of $sandbox[total] nodes";
}
