<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;

/**
 * Bundle class for certificate content type.
 */
class Certificate extends Node implements CertificateInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup|string|null {
    $currentLabel = parent::label();
    if (!is_null($currentLabel)) {
      return $currentLabel;
    }

    $course = $this->getCourse();
    return $course
      ? "Certificate of course {$course->id()}"
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    $this->doCertificatePreSave();

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getCourse(): ?SolutionInterface {
    return $this->course?->entity ?? NULL;
  }

  /**
   * Sets the title and the owner of the certification.
   */
  protected function doCertificatePreSave(): void {
    if ($this->isSyncing()) {
      return;
    }

    $course = $this->getCourse();
    if (!$course instanceof SolutionInterface) {
      throw new \LogicException('Certificate must have a course (solution node)');
    }
    if (!$course->isPublished()) {
      throw new \LogicException('Certificate course (solution node) must be published');
    }

    // Anonymous cannot own a certificate.
    if ($this->isNew()) {
      $routeParams = \Drupal::routeMatch()->getParameters()->all();
      $userParam = current(array_filter($routeParams, fn ($param) => $param instanceof UserInterface)) ?: NULL;
      if (!$userParam instanceof UserInterface) {
        throw new \LogicException("Certificate must be added from the owner user's route context, or the owner must be set explicitly and the node must be marked as syncing before save.");
      }

      $this->setOwner($userParam);
    }

    $this->setTitle(mb_substr("Certificate of course {$course->label()}", 0, 255));
  }

}
