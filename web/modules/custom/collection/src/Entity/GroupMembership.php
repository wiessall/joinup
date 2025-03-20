<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgMembership;

/**
 * Bundle class for default organic group memberships.
 */
class GroupMembership extends OgMembership implements CodeOfConductMembershipInterface {

  /**
   * Code of Conduct document field name.
   */
  protected const string COC_DOCUMENT_FIELD = 'coc_document';

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
    $fieldDocument = $this->get(self::COC_DOCUMENT_FIELD);
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
      ->set(self::COC_DOCUMENT_FIELD,
        [
          'target_id' => $codeOfConduct->id(),
          'target_revision_id' => $codeOfConduct->getRevisionId(),
        ],
      );
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->isNew()) {
      // In every case a membership is created, the code of conduct either has
      // to be accepted prior to the membership creation, or is not valid for
      // the group. In either case, the full membership is always granted on
      // creation. This is also a safe add for any case in the API and tests as
      // it avoids handling it explicitly everywhere. The only other scenarios
      // we have, is either the drop of the full membership, which happens with
      // a direct query and only for collections, or the re-granting of the
      // full membership, which is an update, and thus, will not be affected by
      // this.
      // @see \Drupal\collection\Entity\Collection::resetFullMemberships
      // @see \Drupal\collection\Entity\Collection::grantFullMembership
      $fullMemberRoleId = $this->getGroupEntityType() . '-' . $this->getGroupBundle() . '-full_member';
      $fullMemberRole = $this->entityTypeManager()->getStorage('og_role')->load($fullMemberRoleId);
      $this->addRole($fullMemberRole);
    }
    parent::preSave($storage);
  }

}
