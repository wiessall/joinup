<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgMembership;

/**
 * Bundle class for default organic group memberships.
 */
class GroupMembership extends OgMembership implements CodeOfConductMembershipInterface {

  /**
   * Gets the document field name.
   */
  protected function getDocumentFieldName(): string {
    return 'coc_document';
  }

  /**
   * Gets id of the last accepted revision.
   *
   * @return string|null
   *   Document id.
   */
  protected function getAcceptedValues(): ?array {
    $fieldDocument = $this->get($this->getDocumentFieldName());
    if ($fieldDocument->isEmpty()) {
      return NULL;
    }

    return $fieldDocument->get(0)->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function isLastRevisionAccepted(CollectionInterface $node): bool {
    $codeOfConduct = $node->getCodeOfConduct();
    if (!$codeOfConduct) {
      return FALSE;
    }

    $existing = [
      'target_id' => $codeOfConduct->id(),
      'target_revision_id' => $codeOfConduct->getRevisionId(),
    ];

    return $existing === $this->getAcceptedValues();
  }

  /**
   * {@inheritdoc}
   */
  public function setAcceptedRevisionId(NodeInterface $codeOfConduct): self {
    return $this
      ->set('coc_acceptance', \Drupal::time()->getCurrentTime())
      ->set($this->getDocumentFieldName(),
        [
          'target_id' => $codeOfConduct->id(),
          'target_revision_id' => $codeOfConduct->getRevisionId(),
        ],
      );
  }

}
