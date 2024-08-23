<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Provides a base class for entity tests in Joinup.
 *
 * This class is not intended for generic kernel tests but only for entity
 * tests. It will be renamed to JoinupEntityKernelTestBase to avoid confusion.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4542
 *
 * Mainly, assures the connection to the triple store database.
 *
 * IMPORTANT! You should not use real RDF entity bundles for testing because the
 * test is using the same backend storage as the main site and you can end up
 * with changes to the main site content. Create your own RDF entity bundles for
 * testing purposes, like the one provided in the rdf_entity_test.module. That
 * module uses a dedicated testing graphs, (http://example.com/dummy/published
 * and http://example.com/dummy/draft). This base class enables, at startup, the
 * rdf_entity_test.module and takes care of deleting testing data. For other
 * custom testing data that you are adding for testing, you should take care of
 * cleaning it after the test. You can extend the tearDown() method for this
 * purpose.
 */
abstract class JoinupKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

}
