<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_licence\ExistingSite;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\joinup_validation\Plugin\Validation\Constraint\UniqueFieldInBundleConstraint;

/**
 * Tests the validation on the licence bundle entity.
 */
#[Group('entity_validation')]
class UniqueSpdxReferenceTest extends JoinupExistingSiteTestBase {

  /**
   * Test that references to SPDX licences are unique.
   */
  public function testUniqueSpdxReference(): void {
    $licence_type = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri('http://purl.org/adms/licence/PD');
    $spdx_licence = MappedTerm::loadByUri('http://spdx.org/licenses/GPL-2.0-or-later');

    $this->createNode([
      'title' => 'Licence 1',
      'type' => 'licence',
      'description' => ['value' => 'Some description'],
      'licence_type' => $licence_type,
      'spdx_licence' => $spdx_licence,
    ]);
    $licence = $this->createNode([
      'title' => 'Licence 2',
      'type' => 'licence',
      'description' => ['value' => 'Some description'],
      'licence_type' => $licence_type,
      'spdx_licence' => $spdx_licence,
    ]);

    /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations */
    $violations = $licence->validate();
    $this->assertCount(1, $violations);
    $violation = $violations[0];
    $this->assertSame(UniqueFieldInBundleConstraint::class, get_class($violation->getConstraint()));
    $this->assertSame('spdx_licence', $violation->getPropertyPath());
  }

}
