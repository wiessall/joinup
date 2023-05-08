<?php

declare(strict_types = 1);

namespace Drupal\joinup_like\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying a modal dialog.
 */
class ModalDialogController extends ControllerBase {

  /**
   * Displays a modal dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response object that contains the command to display a modal
   *   dialog.
   */
  public function render() {
    // Create an AJAX response object.
    $response = new AjaxResponse();

    // Add an OpenModalDialogCommand object to the AJAX response object.
    $response->addCommand(
      new OpenModalDialogCommand(
        // Set the title of the modal dialog.
        $this->t('You are not logged in'),

        // Set the content of the modal dialog to a render array
        // with a theme hook.
        ['#theme' => 'modal_dialog'],

        // Set the width of the modal dialog to 700 pixels.
        ['width' => '700']
      )
    );

    // Return the AJAX response object.
    return $response;
  }

}
