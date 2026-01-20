<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * SPDX licences context.
 */
class SpdxContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Vocabulary machine name.
   */
  const string TAXONOMY_NAME = 'spdx_licence';

  /**
   * Creates a number of SPDX licence records.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | title       | ID  | see also                                                                                         | text                                                                                               |
   * | MIT License | MIT | opensource.org - https://opensource.org/licenses/MIT, mit-license.org - https://mit-license.org/ | "MIT License\n\nCopyright ©2019\n\nPermission is hereby granted, free of charge, to any person..." |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $data
   *   The data for the licences to create, in table format.
   *
   * @throws \Exception
   *   Thrown when a column has a title that is not recognized.
   */
  #[Given('(the following )SPDX licences:')]
  public function givenSpdxLicences(TableNode $data): void {
    $aliases = $this->getTaxonomyFieldMappings(self::TAXONOMY_NAME);
    foreach ($data->getColumnsHash() as $licence_data) {
      $values = (object) ['vocabulary_machine_name' => self::TAXONOMY_NAME];

      // Replace the human-readable aliases with the field machine names.
      foreach ($licence_data as $key => $value) {
        if (!array_key_exists($key, $aliases)) {
          throw new \Exception("Unknown column '$key' in the data table.");
        }
        if ($key === 'description') {
          $value = [
            'value' => $value,
            'format' => 'content_editor',
          ];
        }
        $values->{$aliases[$key]} = $value;
      }

      $this->termCreate($values);
    }
  }

  /**
   * Navigates to the canonical page of the SPDX licence.
   *
   * @param string $licence
   *   The SPDX licence label.
   *
   * @throws \Exception
   *   Thrown when the licence does not exist, or cannot be loaded.
   */
  #[Given('I visit the :licence SPDX licence')]
  #[Given('I go to the( homepage of the) :licence SPDX licence')]
  public function visitSpdxLicence(string $licence): void {
    $term = $this->getEntityByLabel('taxonomy_term', $licence);
    $this->visitPath($term->toUrl()->toString());
  }

}
