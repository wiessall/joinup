<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\joinup_core\Traits\FaultTolerantAssetControllerTrait;
use Drupal\system\Controller\JsAssetController;

/**
 * Replacement controller for JS asset route.
 *
 * This controller returns empty file when request is wrong. The file is also
 * written to the file system, so server load also might be reduced a bit.
 */
class JoinupJsAssetController extends JsAssetController {

  use FaultTolerantAssetControllerTrait;

}
