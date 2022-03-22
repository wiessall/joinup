<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form class for 'rdf_meta_entity_type' add/edit.
 */
class RdfMetaEntityTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form['description'] = $this->t('UI not supported yet. The form is added so that the field_ui routes work properly.');
    return $form;
  }

}
