<?php

declare(strict_types=1);

namespace Drupal\eif;

/**
 * Provides an interface for EIF Toolbox helper class.
 */
interface EifInterface {

  /**
   * The EIF toolbox solution ID.
   *
   * @var string
   */
  public const EIF_ID = 'http://data.europa.eu/w21/405d8980-3f06-4494-b34a-46c388a38651';

  /**
   * The node ID of the EIF Online Assistant solutions custom page.
   *
   * @var int
   */
  public const EIF_ONLINE_ASSISTANT = 705372;

  /**
   * The NID target of the solution category filter.
   *
   * @var null|int
   */
  public const EIF_SOLUTION_CATEGORY_FILTER_TARGET_ID = NULL;

  /**
   * The NID target of the principle filter.
   *
   * @var int
   */
  public const EIF_PRINCIPLE_FILTER_TARGET_ID = 707251;

  /**
   * The NID target of the layer filter.
   *
   * @var int
   */
  public const EIF_LAYER_FILTER_TARGET_ID = 707252;

  /**
   * The NID target of the model filter.
   *
   * @var int
   */
  public const EIF_MODEL_FILTER_TARGET_ID = 707200;

}
