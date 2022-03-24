<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\meta_entity\MetaEntityAccessControlHandler;

/**
 * Provides an access control handler for RDF meta entity.
 */
class RdfMetaEntityAccessControlHandler extends MetaEntityAccessControlHandler { }
