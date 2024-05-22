<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGenerator;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests VitalityIndexGenerator.
 *
 * @coversDefaultClass \Drupal\eu_oss_vitality_index\VitalityIndexGenerator
 *
 * @group eu_oss_catalogue
 * @group eu_oss_vitality_index
 **/
class VitalityIndexGeneratorTest extends UnitTestCase {

  /**
   * Test exception method.
   */
  public function testException(): void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $logger->error(Argument::any(), Argument::type('array'))->shouldBeCalled();

    $ossSolution = $this->prophesize(OssSolutionInterface::class);
    $ossSolution->getUrl()->willReturn('https://www.repo.com/dummy.git');
    $ossSolution->get('oss_vitality_index_range')->shouldNotBeCalled();

    $vitalityIndexGenerator = new VitalityIndexGenerator($entityTypeManager->reveal(), $logger->reveal());
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());
  }

}
