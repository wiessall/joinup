<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_licence\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_validation\Plugin\Validation\Constraint\UniqueFieldInBundleConstraint;

/**
 * Tests the validation on the licence bundle entity.
 *
 * @group entity_validation
 */
class UniqueSpdxReferenceTest extends JoinupExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the testing taxonomy term 'Attribution' for the licence type.
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('licence_type');
    $this->createTerm($vocabulary, [
      'name' => 'Attribution',
      'description' => 'Attribution licence type',
      'uri' => 'http://purl.org/adms/licencetype/Attribution',
    ]);
  }

  /**
   * Test that references to SPDX licences are unique.
   */
  public function testUniqueSpdxReference(): void {
    $licence_type = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri('http://purl.org/adms/licencetype/Attribution');

    $spdx = $this->createRdfEntity([
      'label' => 'Test SPDX',
      'rid' => 'spdx_licence',
    ]);
    $spdx_id = $spdx->id();

    $this->createRdfEntity([
      'label' => 'Licence 1',
      'rid' => 'licence',
      'field_licence_description' => ['value' => 'Some description'],
      'field_licence_type' => $licence_type,
      'field_licence_spdx_licence' => $spdx_id,
    ]);
    $licence = $this->createRdfEntity([
      'label' => 'Licence 2',
      'rid' => 'licence',
      'field_licence_description' => ['value' => 'Some description'],
      'field_licence_type' => $licence_type,
      'field_licence_spdx_licence' => $spdx_id,
    ]);

    /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations */
    $violations = $licence->validate();
    $this->assertCount(1, $violations);
    $violation = $violations[0];
    $this->assertSame(UniqueFieldInBundleConstraint::class, get_class($violation->getConstraint()));
    $this->assertSame('field_licence_spdx_licence', $violation->getPropertyPath());
  }

}
