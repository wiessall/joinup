<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_seo\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Tests\UnitTestCase;
use Drupal\joinup_seo\JoinupSeoExportHelperInterface;
use Drupal\rdf_entity\RdfInterface;
use ML\JsonLD\Exception\JsonLdException;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * A very basic regression test for Joinup SEO's page attachment hook.
 *
 * @group joinup_seo
 */
class JoinupSeoPageAttachmentsTest extends UnitTestCase implements LoggerInterface {

  use LoggerTrait;

  /**
   * Local storage for the logged messages.
   */
  protected static array $logStorage = [];

  /**
   * Tests joinup_seo_page_attachments().
   *
   * @param bool $throwException
   *   Whether exception must be thrown by the serializer while it tries to
   *   process an entity.
   * @param array $expectedHeadAttachment
   *   The expected HTML <head> attachments added by Joinup SEO.
   *
   * @dataProvider providerTestData
   *
   * @covers joinup_seo_page_attachments
   */
  public function testJoinupSeoPageAttachments(bool $throwException, array $expectedHeadAttachment): void {
    $rdfEntity = $this->prophesize(RdfInterface::class);
    $rdfEntity->id()->willReturn($id = 'http://foo.bar/baz');
    $rdfEntity->label()->willReturn($label = 'RDF Entity Label');

    $currentRouteMatch = $this->prophesize(CurrentRouteMatch::class);
    $currentRouteMatch->getRouteName()->willReturn('entity.rdf_entity.canonical');
    $currentRouteMatch->getParameter()->withArguments([Argument::exact('rdf_entity')])
      ->willReturn($rdfEntity->reveal());

    $seoHelper = $this->prophesize(JoinupSeoExportHelperInterface::class);

    if ($throwException) {
      $seoHelper->exportRdfEntityMetadata(Argument::exact($rdfEntity->reveal()))
        ->willThrow(new JsonLdException(JsonLdException::INVALID_TYPED_VALUE, 'Message'));
    }
    else {
      $seoHelper->exportRdfEntityMetadata(Argument::exact($rdfEntity->reveal()))
        ->willReturn(json_encode(['label' => $label, 'id' => $id]));
    }

    $loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $loggerFactory->get(Argument::any())->willReturn($this);

    $container = new ContainerBuilder();
    $container->set('current_route_match', $currentRouteMatch->reveal());
    $container->set('joinup_seo.export_helper', $seoHelper->reveal());
    $container->set('logger.factory', $loggerFactory->reveal());
    \Drupal::setContainer($container);

    $initialArray = [];
    include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'joinup_seo.module';
    joinup_seo_page_attachments($initialArray);

    $this->assertEquals($expectedHeadAttachment, $initialArray);
    $this->assertCount((int) $throwException, static::$logStorage);

    if ($throwException) {
      $actualMessage = reset(static::$logStorage);
      $this->assertArrayHasKey('@backtrace', $actualMessage['context']);
      $this->assertIsString($actualMessage['context']['@backtrace']);
      unset($actualMessage['context']['@backtrace']);
      $this->assertEquals(
        [
          'level' => 'error',
          'message' => "The RDF entity '@entity-label' (id: ':entity-id') cannot be serialized to JsonLD object. @exception-message",
          'context' => [
            '@entity-label' => 'RDF Entity Label',
            ':entity-id' => 'http://foo.bar/baz',
            '@exception-message' => 'Message',
          ],
        ],
        $actualMessage
      );
    }
  }

  /**
   * Data provider for ::testJoinupSeoPageAttachments.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestData(): array {
    return [
      'Serializable object' => [
        'Serializer throws exception' => FALSE,
        'Expected <head> attachment' => [
          '#attached' => [
            'html_head' => [
              [
                [
                  '#tag' => 'script',
                  '#attributes' => ['type' => 'application/ld+json'],
                  '#value' => '{"label":"RDF Entity Label","id":"http:\/\/foo.bar\/baz"}',
                ],
                'rdf_entity_ld_json',
              ],
            ],
          ],
        ],
      ],
      'Non-serialiazble object' => [
        'Serializer throws exception' => TRUE,
        'Expected <head> attachment' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    static::$logStorage[] = [
      'level' => $level,
      'message' => (string) $message,
      'context' => $context,
    ];
  }

}
