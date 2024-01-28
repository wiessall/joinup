<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync\Model;

/**
 * RDF backend sync mode.
 */
enum SyncMode: string {

  // The RDF backend is not updated in entity insert/update/delete. This is
  // useful in operations such as installation, when we want to avoid any
  // interaction with the RDF backend. In such cases, the backend is
  // synchronized manually, later.
  case NONE = 'none';

  // The RDF backend synchronization is queued. A worker is doing the actual
  // synchronization, mostly on cron. This mode is useful in production, where
  // we avoid overhead when a node is saving.
  case DELAYED = 'delayed';

  // The RDF backens synchronization is done instantly. Useful in local
  // development and in testing pipelines when we want to assert fresh data and
  // the performance is not paramount.
  case INSTANT = 'instant';

}
