<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a view bulk operations attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class VboAction extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $type = NULL,
    public readonly ?bool $confirm = NULL,
    public readonly ?string $confirm_form_route_name = NULL,
    public readonly ?bool $pass_context = NULL,
    public readonly ?bool $pass_view = NULL,
    public readonly array $requirements = [],
  ) {}

}
