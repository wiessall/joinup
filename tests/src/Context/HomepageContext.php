<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Mink\Element\NodeElement;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\entityqueue\EntitySubqueueInterface;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Behat step definitions to interact with elements on the Joinup homepage.
 */
class HomepageContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;

  /**
   * Paragraph translations to revert when the scenario ends.
   *
   * Each item is an array describing one configurable listing translation added
   * to a pre-existing topic:
   * @code
   * [
   *   <topic ID>,
   *   <topics_paragraph delta>,
   *   <original target revision ID>,
   *   <paragraph ID>,
   *   <language code>,
   * ]
   * @endcode
   */
  protected array $translatedConfigurableListings = [];

  /**
   * Translates the configurable listing paragraph.
   *
   * Topic bodies reference their paragraphs through a non-translatable field,
   * so the (shared) paragraph carries its own translations. This adds a
   * translation to the configurable listing paragraph and points the parent at
   * the resulting revision so the translation is rendered.
   *
   * @param string $langcode
   *   The language code of the translation to set, e.g. 'el'.
   * @param string $term
   *   The label of the topic taxonomy term.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   The translatable fields to set, as "field"/"value" rows.
   */
  #[Given('the :langcode translation of the configurable listing in the :term topic has:')]
  public function translateConfigurableListing(string $langcode, string $term, TableNode $fields): void {
    $topic = $this->getEntityByLabel('taxonomy_term', $term, 'topic');

    $delta = NULL;
    $paragraph = NULL;
    foreach ($topic->get('topics_paragraph') as $index => $item) {
      if ($item->entity instanceof ParagraphInterface && $item->entity->bundle() === 'configurable_listing') {
        $delta = $index;
        $paragraph = $item->entity;
        break;
      }
    }
    if ($paragraph === NULL) {
      throw new \RuntimeException(sprintf('No configurable listing paragraph found in the "%s" topic.', $term));
    }

    $original_target_revision_id = $topic->get('topics_paragraph')->get($delta)->target_revision_id;
    $added_translation = !$paragraph->hasTranslation($langcode);
    $translation = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph->addTranslation($langcode, $paragraph->toArray());
    foreach ($fields->getHash() as $row) {
      $translation->set($row['field'], $row['value']);
    }
    $translation->save();

    // The paragraph save created a new revision. Point the parent at it so the
    // translation is rendered, then save the parent.
    $topic->get('topics_paragraph')->get($delta)->set('target_revision_id', $paragraph->getRevisionId());
    $topic->save();

    if ($added_translation) {
      $this->translatedConfigurableListings[] = [
        $topic->id(),
        $delta,
        $original_target_revision_id,
        $paragraph->id(),
        $langcode,
      ];
    }
  }

  /**
   * Reverts configurable listing translations added to topics.
   */
  #[AfterScenario]
  public function cleanUpTranslatedConfigurableListings(): void {
    if (empty($this->translatedConfigurableListings)) {
      return;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $term_storage = $entity_type_manager->getStorage('taxonomy_term');
    $paragraph_storage = $entity_type_manager->getStorage('paragraph');

    foreach ($this->translatedConfigurableListings as $record) {
      [$tid, $delta, $original_target_revision_id, $paragraph_id, $langcode] = $record;
      $topic = $term_storage->load($tid);
      if ($topic !== NULL) {
        $item = $topic->get('topics_paragraph')->get($delta);
        if ($item !== NULL) {
          $item->set('target_revision_id', $original_target_revision_id);
          $topic->save();
        }
      }

      $paragraph = $paragraph_storage->load($paragraph_id);
      if ($paragraph instanceof TranslatableInterface && $paragraph->hasTranslation($langcode)) {
        $paragraph->removeTranslation($langcode);
        $paragraph->save();
      }
    }
    $this->translatedConfigurableListings = [];
  }

  /**
   * Asserts the configurable listing of a topic has a given translation.
   *
   * @param string $term
   *   The label of the topic taxonomy term.
   * @param string $langcode
   *   The language code of the expected translation, e.g. 'el'.
   */
  #[Then('the configurable listing of the :term topic should have a :langcode translation')]
  public function assertConfigurableListingHasTranslation(string $term, string $langcode): void {
    if (!$this->configurableListingHasTranslation($term, $langcode)) {
      throw new \RuntimeException(sprintf('The configurable listing of the "%s" topic does not have a "%s" translation.', $term, $langcode));
    }
  }

  /**
   * Asserts the configurable listing of a topic has no given translation.
   *
   * @param string $term
   *   The label of the topic taxonomy term.
   * @param string $langcode
   *   The language code of the unexpected translation, e.g. 'el'.
   */
  #[Then('the configurable listing of the :term topic should not have a :langcode translation')]
  public function assertConfigurableListingHasNoTranslation(string $term, string $langcode): void {
    if ($this->configurableListingHasTranslation($term, $langcode)) {
      throw new \RuntimeException(sprintf('The configurable listing of the "%s" topic unexpectedly has a "%s" translation.', $term, $langcode));
    }
  }

  /**
   * Checks whether a topic's configurable listing paragraph is translated.
   *
   * @param string $term
   *   The label of the topic taxonomy term.
   * @param string $langcode
   *   The language code of the translation to check.
   *
   * @return bool
   *   If the configurable listing carries a translation in the given language.
   */
  protected function configurableListingHasTranslation(string $term, string $langcode): bool {
    $topic = $this->getEntityByLabel('taxonomy_term', $term, 'topic');
    foreach ($topic->get('topics_paragraph') as $item) {
      if ($item->entity instanceof ParagraphInterface && $item->entity->bundle() === 'configurable_listing') {
        return $item->entity->hasTranslation($langcode);
      }
    }
    return FALSE;
  }

  /**
   * Checks the contents of the "Explore" block.
   *
   * This is showing a number of content items (collections, solutions, news and
   * events) on the homepage.
   *
   * This also checks if the content is in the correct order and if the total
   * number of content items is correct.
   *
   * @codingStandardsIgnoreStart
   * Table format:
   *  | type       | title             | date                   | description              |
   *  | solution   | Cities of Italy   | 2020-01-01 17:36 +0200 | Sum is therefore         |
   *  | collection | Products of Italy | 2019-08-14 17:36 +0200 | Lorem Ipsum is therefore |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table containing the expected content of the explore section.
   */
  #[Then('the explore section should contain the following content:')]
  public function assertExplore(TableNode $table): void {
    $columns = $table->getColumnsHash();
    $articles = $this->getSession()->getPage()->findAll('css', '.block-joinup-front-page-explore-block article');
    $this->assertSameValues(count($columns), count($articles), sprintf('Expected %d items in the "Explore" section but found %d items.', count($columns), count($articles)));

    foreach ($columns as $expected_data) {
      $actual_data = array_shift($articles);
      $type = $expected_data['type'];

      if (in_array($type, ['collection', 'solution'])) {
        $entity = $this->getEntityByLabel('node', $expected_data['title']);
      }
      else {
        $entity = $this->getEntityByLabel('node', $expected_data['title']);
      }
      $read_more_url = $this->getEntityUri($entity);

      // Check that title links to the canonical page of the
      // news, event, solution and collection.
      $xpath = '//h3/a[@href = "' . $this->getEntityUri($entity) . '" and contains(., "' . $expected_data['title'] . '")]';
      $this->assertCondition(!empty($actual_data->find('xpath', $xpath)), sprintf('%s "%s" does not have the correct title which links to the canonical page.', $type, $expected_data['title']));

      // Check the body/description text. XPath varies in case of news and
      // events, because 'filtered_entity_reference_bundles' formatter does not
      // use field theme functions.
      $description_element = $actual_data->find('css', '.explore-item__description > div')
        ?: $actual_data->find('css', '.explore-item__description');
      $this->assertCondition($description_element instanceof NodeElement, sprintf('Did not find a description for the %s "%s" in the "Explore" section.', $type, $expected_data['title']));

      $actual_description = $description_element->getHtml();
      // Note: Please, take care, that the following will not work properly if
      // twig debug is enabled. In that case, the output will contain a lot of
      // HTML comments which will not be stripped out by the formatter.
      $this->assertSameValues($expected_data['description'], trim($actual_description), sprintf('The body text for the %s "%s" in the "Explore" section does not contain the expected text.', $type, $expected_data['title']));

      // Check date.
      $expected_date = date("d/m/Y", strtotime($expected_data['date']));
      $xpath = '//*[text() = "' . $expected_date . '"]';
      $this->assertCondition(!empty($actual_data->find('xpath', $xpath)), sprintf('The date for the %s "%s" in the "Explore" section does not contain the expected format.', $type, $expected_data['title']));

      // Check that a "Read more" link is present which leads to the canonical
      // page of the news, event, solution and collection.
      $xpath = '//a[@href = "' . $read_more_url . '" and contains(@class, "read-more")]';
      $this->assertCondition(!empty($actual_data->find('xpath', $xpath)), sprintf('%s "%s" does not have a "Read more" link which leads to the canonical page.', $type, $expected_data['title']));
    }
  }

  /**
   * Checks the contents of the "User Comments" tab of the "Explore" block.
   *
   * Unlike the other tabs this one mixes bundles and is ordered by the date of
   * the newest comment on each item, so it is asserted separately.
   *
   * @codingStandardsIgnoreStart
   * Table format:
   *  | title              | date       |
   *  | Ongoing discussion | 04/03/2026 |
   *  | Interesting news   | 02/03/2026 |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The expected content, in the expected order.
   */
  #[Then('the user comments section should contain the following content:')]
  public function assertUserComments(TableNode $table): void {
    $expected_rows = $table->getColumnsHash();
    $tiles = $this->getSession()->getPage()->findAll('css', '#user_comments .glide__slides > *');
    $this->assertSameValues(count($expected_rows), count($tiles), sprintf('Expected %d items in the "User Comments" tab but found %d.', count($expected_rows), count($tiles)));

    foreach ($expected_rows as $expected) {
      // Shifting keeps the assertion order-sensitive: the tab must list the
      // most recently commented content first.
      $tile = array_shift($tiles);
      $title = $expected['title'];

      $this->assertCondition(
        str_contains($tile->getText(), $title),
        sprintf('The "User Comments" tab does not show "%s" in the expected position.', $title),
      );

      // The date of the newest comment, in the tile footer.
      $this->assertCondition(
        !empty($tile->find('xpath', '//*[text() = "' . $expected['date'] . '"]')),
        sprintf('The comment date "%s" was not found on the tile for "%s".', $expected['date'], $title),
      );

      // "See comment" must deep link to the comment rather than to the top of
      // the page, otherwise the visitor has to hunt for it.
      $link = $tile->find('xpath', '//a[contains(., "See comment")]');
      $this->assertCondition(
        $link instanceof NodeElement,
        sprintf('No "See comment" link was found on the tile for "%s".', $title),
      );
      $this->assertCondition(
        (bool) preg_match('/#comment-\d+$/', (string) $link->getAttribute('href')),
        sprintf('The "See comment" link for "%s" does not point at a specific comment, but at "%s".', $title, $link->getAttribute('href')),
      );
    }
  }

  /**
   * Adds the given topic terms to the homepage topics queue.
   */
  #[Given('/^the following topics are enabled in the homepage:$/')]
  public function theFollowingTopicsAreEnabledInTheHomepage(TableNode $table): void {
    $entity_queue = \Drupal::entityTypeManager()->getStorage('entity_subqueue')->load('homepage_topics');
    \assert($entity_queue instanceof EntitySubqueueInterface);
    foreach ($table->getColumn(0) as $label) {
      $topic = $this->getEntityByLabel('taxonomy_term', $label);
      $entity_queue->addItem($topic);
    }
    $entity_queue->save();
  }

  /**
   * Cleans up the homepage topics queue and resets topic vocabulary terms.
   */
  #[AfterScenario('@topic&&@api')]
  public function resetTopicVocabularyQueue(): void {
    \Drupal::entityTypeManager()->getStorage('entity_subqueue')
      ->load('homepage_topics')
      ->clearItems()
      ->set('block_label', NULL)
      ->save();

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'topic')
      ->execute();
    if ($ids) {
      foreach ($term_storage->loadMultiple($ids) as $term) {
        $term
          ->set('topics_paragraph', NULL)
          ->set('abstract', NULL)
          ->set('image', NULL)
          ->set('description', NULL)
          ->save();
      }
    }
  }

}
