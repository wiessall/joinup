<?php

declare(strict_types=1);

namespace Drupal\eif;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an EIF Wrapper entity type.
 *
 * @todo Cannot delete the entity type class in a single deployment. Delete the
 *   entities but postpone the class deletion to ISAICP-8376.
 */
interface EifWrapperInterface extends ContentEntityInterface {

}
