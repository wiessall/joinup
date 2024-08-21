<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_video\Kernel;

use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Test the media bundle default names.
 *
 * @group joinup_video
 */
class MediaNameTest extends MediaKernelTestBase {

  /**
   * The plugin under test.
   *
   * @var \Drupal\video_embed_media\Plugin\media\Source\VideoEmbedField
   */
  protected $plugin;

  /**
   * The created media type.
   *
   * @var \Drupal\media\Entity\MediaType
   */
  protected $entityType;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'video_embed_field',
    'video_embed_media',
    'joinup_video',
    'media',
    'file',
    'views',
  ];

  /**
   * Test cases for ::testMediaName().
   */
  public function mediaNameTestCases(): array {
    return [
      'YouTube' => [
        'https://www.youtube.com/watch?v=gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'YouTube - youtu.be' => [
        'https://youtu.be/gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'YouTube - /embed URL' => [
        'https://www.youtube.com/embed/gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'YouTube - no-cookie URL' => [
        'https://www.youtube-nocookie.com/embed/gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'Youtube Playlist' => [
        'https://www.youtube.com/watch?v=bbOCX3vlMyE&list=PLpeDXSh4nHjS7I_OIrHrZXb00xSDZ7zBz',
        'YouTube Playlist | DrupalCon',
      ],
      'Vimeo' => [
        'https://vimeo.com/21681203',
        'Vimeo | Drupal Commerce at DrupalCon Chicago',
      ],
      'Google Docs' => [
        'https://docs.google.com/document/d/1S8gJeCD_vDjbM2JKk8Ojkt-6Uj_fdn_NQPdzHeQnn0Y/edit?usp=sharing',
        'Google Docs | 1S8gJeCD_vDjbM2JKk8Ojkt-6Uj_fdn_NQPdzHeQnn0Y',
      ],
      'Internal path' => [
        'contact',
        'Internal path | contact',
      ],
      'AV Services - Current URL' => [
        'https://audiovisual.ec.europa.eu/en/video/I-136289',
        'EC Audiovisual Services | United Kingdom notification - Article 50, Brussels: press statement by Donald Tusk, President of the European Council',
      ],
      'AV Services - Current embed URL' => [
        'https://audiovisual.ec.europa.eu/corporateplayer/index.html?video=I-136289',
        'EC Audiovisual Services | United Kingdom notification - Article 50, Brussels: press statement by Donald Tusk, President of the European Council',
      ],
      'AV Services - Old URL' => [
        'https://ec.europa.eu/avservices/play.cfm?ref=I136289',
        'EC Audiovisual Services | United Kingdom notification - Article 50, Brussels: press statement by Donald Tusk, President of the European Council',
      ],
      'AV Services - Old embed URL' => [
        'https://audiovisual.ec.europa.eu/embed/index.html?ref=I-136289',
        'EC Audiovisual Services | United Kingdom notification - Article 50, Brussels: press statement by Donald Tusk, President of the European Council',
      ],
      'AV Services - EU short URL' => [
        'https://europa.eu/!dV74uw',
        'EC Audiovisual Services | United Kingdom notification - Article 50, Brussels: press statement by Donald Tusk, President of the European Council',
      ],
      'European Commission Webcast' => [
        'https://webcast.ec.europa.eu/iprs-and-innovation-procurement-22-01-20/embed',
        'European Commission Webcast | iprs-and-innovation-procurement-22-01-20',
      ],
      'Prezi' => [
        'https://prezi.com/embed/lspajpgcpx1k/',
        'Prezi | Example Embedded Prezi',
      ],
      'Slideshare' => [
        'https://www.slideshare.net/slideshow/embed_code/key/hJ3x3pTrtGaatQ',
        'Slideshare | Sample SlideShare Presentation',
      ],
    ];
  }

  /**
   * Test the media names.
   *
   * @dataProvider mediaNameTestCases
   */
  public function testMediaName($input, $expected): void {
    $field_name = $this->plugin->getSourceFieldDefinition($this->entityType)->getName();
    $entity = Media::create([
      'bundle' => $this->entityType->id(),
      $field_name => [['value' => $input]],
    ]);
    $actual = $this->plugin->getMetadata($entity, 'default_name');
    $this->assertEquals($expected, $actual);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['video_embed_field', 'video_embed_media']);
    $this->entityType = $this->createMediaType('video_embed_field');
    $this->plugin = $this->entityType->getSource();
  }

}
