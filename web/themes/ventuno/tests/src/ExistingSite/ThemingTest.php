<?php

declare(strict_types=1);

namespace Drupal\Tests\ventuno\ExistingSite;

use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\joinup_test\Traits\ParagraphTrait;
use Drupal\file\FileInterface;

/**
 * Test description.
 *
 * @group custom_page
 */
final class ThemingTest extends JoinupExistingSiteTestBase {

  use TestFileCreationTrait;
  use ParagraphTrait;

  /**
   * {@inheritdoc}
   */
  protected string $defaultTheme = 'ventuno';

  /**
   * The image file.
   */
  protected FileInterface $testImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a stub image.
    $filepath = $this->generateFile('image.jpg', 1, 1);
    $this->testImage = \Drupal::entityTypeManager()->getStorage('file')->create([
      'uri' => $filepath,
    ]);
    $this->testImage->save();
  }

  /**
   * Tests the short description of the custom page.
   */
  public function testShortDescription(): void {
    $image_paragraph = $this->createParagraph([
      'type' => 'image',
      'field_image' => [
        'target_id' => $this->testImage->id(),
      ],
    ]);

    $simple_text_paragraph = $this->createParagraph([
      'type' => 'simple_paragraph',
      'field_body' => [
        'value' => 'Some alpha',
        'format' => 'full_html',
      ],
    ]);

    $text_paragraph = $this->createParagraph([
      'type' => 'text',
      'field_body' => [
        'value' => ' Some \n <br /> beta ',
        'format' => 'full_html',
      ],
    ]);

    /** @var \Drupal\custom_page\Entity\CustomPageInterface $custom_page */
    $custom_page = $this->createNode([
      'type' => 'custom_page',
      'title' => 'Test custom page',
      'field_paragraphs_body' => [
        [
          'target_id' => $image_paragraph->id(),
          'target_revision_id' => $image_paragraph->getRevisionId(),
        ],
        [
          'target_id' => $simple_text_paragraph->id(),
          'target_revision_id' => $simple_text_paragraph->getRevisionId(),
        ],
        [
          'target_id' => $text_paragraph->id(),
          'target_revision_id' => $text_paragraph->getRevisionId(),
        ],
      ],
    ]);
    $renderer = \Drupal::getContainer()->get('renderer');
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $render_array = $view_builder->view($custom_page);
    $rendered = (string) $renderer->renderRoot($render_array);
    // Assert that the image paragraph is present by asserting the div with the
    // 'paragraph--image' class.
    $this->assertStringContainsString('paragraph--image', $rendered);
    $this->assertStringContainsString(' Some \n <br> beta ', $rendered);

    // Test the tile view mode.
    $render_array = $view_builder->view($custom_page, 'view_mode_tile');
    $rendered = (string) $renderer->renderRoot($render_array);
    // Assert that the image was stripped.
    $this->assertStringNotContainsString('paragraph--image', $rendered);
    $this->assertStringContainsString('>Some alpha Some \n beta<', $rendered);
    $this->assertStringNotContainsString('Read more', $rendered);

    // Test the tile_horizontal view mode.
    $render_array = $view_builder->view($custom_page, 'view_mode_tile_horizontal');
    $rendered = (string) $renderer->renderRoot($render_array);
    // Assert that the image was stripped.
    $this->assertStringNotContainsString('paragraph--image', $rendered);
    $this->assertStringContainsString('>Some alpha Some \n beta<', $rendered);
    $this->assertStringNotContainsString('Read more', $rendered);
  }

}
