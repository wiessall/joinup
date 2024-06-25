<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Component\Utility\Html;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_core\DeployAndSmokeTestHelper;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Smoke test for Interoperable Europe Act document.
 *
 * @group joinup_core
 */
class IeaDocumentContentTest extends JoinupExistingSiteTestBase {

  /**
   * Tests how the text Interoperable Europe Act document is rendered.
   */
  public function testIeaDocumentContent(): void {
    $rawHtml = file_get_contents(DeployAndSmokeTestHelper::IEA_DOCUMENT_RAW_CONTENT_FILE);
    $node = $this->loadIeaNode();
    if (!$node) {
      // Call the deploy function manually.
      DeployAndSmokeTestHelper::createIeaCustomPage();
      $node = $this->loadIeaNode();
    }
    $this->assertInstanceOf(CustomPageInterface::class, $node);
    $simpleParagraph = current(
      \Drupal::entityTypeManager()
        ->getStorage('paragraph')
        ->loadByProperties([
          'uuid' => DeployAndSmokeTestHelper::IEA_DOCUMENT_PARAGRAPH_UUID,
        ])
    );
    $this->assertInstanceOf(ParagraphInterface::class, $simpleParagraph);

    $field = $node->get('field_paragraphs_body');
    $this->assertTrue(in_array(
      [
        'target_id' => $simpleParagraph->id(),
        'target_revision_id' => $simpleParagraph->getRevisionId(),
      ],
      $field->getValue()
    ));
    $renderable = $field->view('default');
    $rendered = \Drupal::service('renderer')->renderPlain($renderable);
    $paragraphDom = Html::load($rendered);
    foreach ($paragraphDom->getElementsByTagName('div') as $item) {
      assert($item instanceof \DOMElement);
      $class = $item->className;
      if (str_contains($class, 'paragraph--simple-paragraph')) {
        $paragraphContentRaw = $item->ownerDocument->saveXml($item);
        break;
      }
    }

    // Drupal renders self-closing tags with slash, so we alter the raw HTML
    // a little bit by adding slash to the <hr> and <br> tags.
    $rawHtml = preg_replace(
      [
        '/<br>/',
        '/<hr\b([^\/>]*)>/',
      ],
      [
        '<br/>',
        '<hr$1/>',
      ],
      $rawHtml
    );

    $this->assertStringContainsString(
      trim(preg_replace('/\s+/', ' ', $rawHtml)),
      trim(preg_replace('/\s+/', ' ', $paragraphContentRaw ?? '')),
    );
  }

  /**
   * Loads the node of Interoperable Europe Act.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The Interoperable Europe Act node, or NULL if it cannot be found by its
   *   UUID.
   */
  protected function loadIeaNode(): ?CustomPageInterface {
    $node = current(
      \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'uuid' => DeployAndSmokeTestHelper::IEA_DOCUMENT_UUID,
        ])
    );
    if ($node) {
      $this->assertInstanceOf(CustomPageInterface::class, $node);
    }
    return $node ?: NULL;
  }

}
