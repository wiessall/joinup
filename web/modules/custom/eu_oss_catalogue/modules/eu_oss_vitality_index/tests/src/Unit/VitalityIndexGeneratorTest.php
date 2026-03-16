<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests VitalityIndexGenerator.
 */
#[CoversClass(VitalityIndexGenerator::class)]
#[Group('eu_oss_catalogue')]
#[Group('eu_oss_vitality_index')]
class VitalityIndexGeneratorTest extends UnitTestCase {

  /**
   * Test exception method.
   */
  public function testErrorLogging(): void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $logger->error(Argument::any(), Argument::type('array'))->shouldBeCalled();
    $fileSystem = $this->prophesize(FileSystemInterface::class);
    $immutableConfig = $this->prophesize(ImmutableConfig::class);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('eu_oss_vitality_index.settings')
      ->willReturn($immutableConfig);

    $ossSolution = $this->prophesize(OssSolutionInterface::class);
    $ossSolution->getUrl()->willReturn('https://www.repo.com/dummy.git');
    $ossSolution->get('oss_vitality_index_range')->shouldNotBeCalled();
    $ossSolution->id()->willReturn(111);

    $vitalityIndexGenerator = new VitalityIndexGenerator(
      $entityTypeManager->reveal(),
      $logger->reveal(),
      $fileSystem->reveal(),
      $configFactory->reveal(),
    );
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());
  }

  /**
   * Test node updates.
   */
  public function testNodeUpdate(): void {
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $fileSystem = $this->prophesize(FileSystemInterface::class);
    $immutableConfig = $this->prophesize(ImmutableConfig::class);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('eu_oss_vitality_index.settings')
      ->willReturn($immutableConfig);

    $stdObject = $this->prophesize('stdClass');
    $stdObject->value = NULL;

    $expectedVitalityIndex = Json::encode(array_reverse(VitalityIndexGeneratorHelper::INDEX_PER_DAY));

    $ossSolution = $this->prophesize(OssSolutionInterface::class);
    $ossSolution->getUrl()->willReturn('https://www.repo.com/dummy.git');
    $ossSolution->id()->willReturn(111);
    $ossSolution->get('oss_vitality_index_range')->willReturn($stdObject);
    $ossSolution->set('oss_vitality_index_range', $expectedVitalityIndex)
      ->willReturn($ossSolution)->shouldBeCalledTimes(1);
    $ossSolution->set('oss_vitality_index', array_sum(VitalityIndexGeneratorHelper::INDEX_PER_DAY) / count(VitalityIndexGeneratorHelper::INDEX_PER_DAY) / 100)
      ->willReturn($ossSolution)->shouldBeCalledTimes(1);
    $ossSolution->save()->willReturn(1)->shouldBeCalledTimes(1);

    $storage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');
    $storage->load(111)->willReturn($ossSolution->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($storage->reveal());

    $vitalityIndexGenerator = new VitalityIndexGeneratorHelper(
      $entityTypeManager->reveal(),
      $logger->reveal(),
      $fileSystem->reveal(),
      $configFactory->reveal(),
    );
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());

    // Process again, the solution should not be updated.
    $stdObject->value = $expectedVitalityIndex;
    $vitalityIndexGenerator->processSolution($ossSolution->reveal());
  }

}
