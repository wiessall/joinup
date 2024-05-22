<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGenerator;
use Prophecy\Argument;

/**
 * Tests VitalityIndexGenerator.
 *
 * @coversDefaultClass \Drupal\eu_oss_vitality_index\VitalityIndexGenerator
 *
 * @group eu_oss_catalogue
 * @group eu_oss_vitality_index
 */
class VitalityIndexGeneratorTest extends UnitTestCase {

  /**
   * Test exception method.
   */
  public function testErrorLogging(): void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $logger->error(Argument::any(), Argument::type('array'))->shouldBeCalled();

    $ossSolution = $this->prophesize(OssSolutionInterface::class);
    $ossSolution->getUrl()->willReturn('https://www.repo.com/dummy.git');
    $ossSolution->get('oss_vitality_index_range')->shouldNotBeCalled();

    $vitalityIndexGenerator = new VitalityIndexGenerator($entityTypeManager->reveal(), $logger->reveal());
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());
  }

  /**
   * Test node updates.
   */
  public function testNodeUpdate(): void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);

    $stdObject = $this->prophesize('stdClass');
    $stdObject->value = NULL;

    $expectedVitalityIndex = Json::encode(array_reverse(VitalityIndexGeneratorHelper::INDEX_PER_DAY));

    $ossSolution = $this->prophesize(OssSolutionInterface::class);
    $ossSolution->getUrl()->willReturn('https://www.repo.com/dummy.git');
    $ossSolution->get('oss_vitality_index_range')->willReturn($stdObject);
    $ossSolution->set('oss_vitality_index_range', $expectedVitalityIndex)
      ->willReturn($ossSolution)->shouldBeCalledTimes(1);
    $ossSolution->set('oss_vitality_index', array_sum(VitalityIndexGeneratorHelper::INDEX_PER_DAY) / count(VitalityIndexGeneratorHelper::INDEX_PER_DAY) / 100)
      ->willReturn($ossSolution)->shouldBeCalledTimes(1);
    $ossSolution->save()->willReturn(1)->shouldBeCalledTimes(1);

    $vitalityIndexGenerator = new VitalityIndexGeneratorHelper($entityTypeManager->reveal(), $logger->reveal());
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());

    // Process again, the solution should not be updated.
    $stdObject->value = $expectedVitalityIndex;
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());
  }

}
