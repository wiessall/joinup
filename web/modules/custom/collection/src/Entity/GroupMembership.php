<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgMembership;

/**
 * Bundle class for default organic group memberships.
 */
class GroupMembership extends OgMembership implements CodeOfConductMembershipInterface {

  /**
   * Code of Conduct document field name.
   */
  protected const string COC_DOCUMENT_FILED = 'coc_document';

  /**
   * Code of Conduct date field name.
   */
  protected const string COC_DATE = 'coc_date';

  /**
   * Retrieves the first accepted Code of Conduct document value.
   *
   * This checks the field storing the Code of Conduct document and returns
   * the first value if available.
   *
   * @return array|null
   *   The first field value as an array, or NULL if the field is empty.
   */
  protected function getAcceptedDocument(): ?array {
    $fieldDocument = $this->get(self::COC_DOCUMENT_FILED);
    if ($fieldDocument->isEmpty()) {
      return NULL;
    }

    return $fieldDocument->first()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function isLastDocumentRevisionAccepted(): bool {
    $collection = $this->getGroup();
    if (!$collection || !$codeOfConduct = $collection->getCodeOfConduct()) {
      return FALSE;
    }

    $latestVersion = [
      'target_id' => $codeOfConduct->id(),
      'target_revision_id' => $codeOfConduct->getRevisionId(),
    ];

    return $latestVersion === $this->getAcceptedDocument();
  }

  /**
   * {@inheritdoc}
   */
  public function setAcceptedDocument(NodeInterface $codeOfConduct): self {
    return $this
      ->set(self::COC_DATE, \Drupal::time()->getCurrentTime())
      ->set(self::COC_DOCUMENT_FILED,
        [
          'target_id' => $codeOfConduct->id(),
          'target_revision_id' => $codeOfConduct->getRevisionId(),
        ],
      );
  }

}
