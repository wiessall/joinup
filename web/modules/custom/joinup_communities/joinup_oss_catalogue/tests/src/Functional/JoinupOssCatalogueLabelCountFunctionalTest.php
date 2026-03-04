<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests count labels for `oss_solution` and `oss_contractor`.
 */
#[Group('joinup_oss_catalogue')]
#[Group('joinup')]
final class JoinupOssCatalogueLabelCountFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['joinup_oss_catalogue'];

  /**
   * Tests count labels for `oss_solution` and `oss_contractor`.
   */
  public function testPluralLabel(): void {
    $bundle = NodeType::load('oss_solution');
    $this->assertNotNull($bundle);
    $this->assertEquals('OSS Solution', (string) $bundle->getSingularLabel());
    $this->assertEquals('OSS Solutions', (string) $bundle->getPluralLabel());
    $this->assertSame([
      "1 open source software solution\x03@count open source software solutions",
    ], $bundle->getCountLabel());

    $bundle = NodeType::load('oss_contractor');
    $this->assertNotNull($bundle);
    $this->assertEquals('open source software contractor', (string) $bundle->getSingularLabel());
    $this->assertEquals('open source software contractors', (string) $bundle->getPluralLabel());
    $this->assertSame([
      "1 open source software contractor\x03@count open source software contractors",
    ], $bundle->getCountLabel());
  }

}
