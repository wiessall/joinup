<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_video\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\rdf_entity\Traits\DrupalTestTraits\RdfEntityCreationTrait;

/**
 * Regression test for ISAICP-7278.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7278
 *
 * @group joinup_video
 */
class InternalPathTest extends JoinupExistingSiteTestBase {

  use RdfEntityCreationTrait;

  /**
   * @covers \Drupal\joinup_video\Plugin\video_embed_field\Provider\InternalPath::renderEmbedCode
   */
  public function testInternalPathVideo(): void {
    $base_url = $GLOBALS['base_url'] . '/';
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'label' => "Sample Collection ",
      'field_ar_state' => 'published',
      'field_ar_description' => [
        // Covering the case when the base URL has been entered twice.
        'value' => ' <iframe src="' . $base_url . $base_url . '" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
        'format' => 'content_editor',
      ],
    ]);
    $this->drupalGet($collection->toUrl());
    $this->assertSession()->pageTextContains('Sample Collection');
    // No iframe has been rendered.
    $this->assertSession()->responseNotContains('<iframe');
  }

}
