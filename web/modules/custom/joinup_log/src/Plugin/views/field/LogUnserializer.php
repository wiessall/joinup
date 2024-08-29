<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Plugin\views\field;

use Drupal\Component\Serialization\PhpSerialize;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\Yaml\Yaml;

/**
 * Field handler to show data of serialized fields.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("log_unserializer")]
class LogUnserializer extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $values->{$this->field_alias};

    $data = PhpSerialize::decode($values->{$this->field_alias});
    if ($data === FALSE) {
      return $value;
    }

    $yamlData = Yaml::dump($data);
    return ['#markup' => '<pre>' . $yamlData . '</pre>'];
  }

}
