<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\ExistingSite;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Form\FormState;
use Drupal\joinup_group\Form\ShareForm;
use Drupal\joinup_group\Form\UnshareForm;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the share forms leave the search index up to date.
 */
#[Group('joinup_group')]
class SharedContentIndexCommitTest extends JoinupExistingSiteTestBase {

  /**
   * The collection the news article is shared on.
   */
  protected CollectionInterface $target;

  /**
   * The news article being shared.
   */
  protected NodeInterface $news;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->target = $this->createCollection();
    $this->news = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $this->createCollection()->id(),
      'field_state' => 'published',
    ]);

    $facilitator = $this->createUser();
    $this->markEntityForCleanup($this->target->createMembership((int) $facilitator->id(), 'facilitator'));
    $this->container->get('current_user')->setAccount($facilitator);
  }

  /**
   * Tests that sharing and unsharing are immediately searchable.
   */
  public function testSharingIsCommittedWithinTheRequest(): void {
    $this->submitShareForm(ShareForm::class);
    $this->assertTrue($this->isIndexedAsSharedOnTarget(), 'The share is immediately searchable.');

    $this->submitShareForm(UnshareForm::class);
    $this->assertFalse($this->isIndexedAsSharedOnTarget(), 'The unshare is immediately searchable.');
  }

  /**
   * Submits one of the share forms for the test news article.
   *
   * @param string $formClass
   *   The class of the form to submit.
   */
  protected function submitShareForm(string $formClass): void {
    $formState = new FormState();
    $formState->setValues([
      'groups' => [$this->target->id() => $this->target->id()],
    ]);
    $this->container->get('form_builder')
      ->submitForm($formClass, $formState, $this->news);
  }

  /**
   * Checks whether the index has the news article shared on the target group.
   *
   * @return bool
   *   TRUE if the index returns the news article for the target group.
   */
  protected function isIndexedAsSharedOnTarget(): bool {
    $query = Index::load('published')->query();
    $query->addCondition('shared_in', [$this->target->id()], 'IN');
    $query->addCondition('nid', (int) $this->news->id());
    $query->range(0, 1);

    return (bool) $query->execute()->getResultCount();
  }

  /**
   * Creates a published collection.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   The collection.
   */
  protected function createCollection(): CollectionInterface {
    return $this->createNode([
      'type' => 'collection',
      'title' => $this->randomMachineName(),
      'field_state' => 'published',
    ]);
  }

}
