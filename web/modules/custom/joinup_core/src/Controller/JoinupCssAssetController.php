<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\joinup_core\Traits\FaultTolerantAssetControllerTrait;
use Drupal\system\Controller\CssAssetController;

/**
 * Replacement controller for CSS asset route.
 *
 * This controller returns empty file when request is wrong. The file is also
 * written to the file system, so server load also might be reduced a bit.
 */
class JoinupCssAssetController extends CssAssetController {

  use FaultTolerantAssetControllerTrait;

}
