<?php

declare(strict_types=1);

namespace Drupal\contact_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the response for the contact form route.
 */
class ContactFormController extends ControllerBase {

  /**
   * Constructs a new ContactFormController object.
   *
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path validator.
   */
  public function __construct(
    protected readonly PathValidatorInterface $pathValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('path.validator'),
    );
  }

  /**
   * Shows the contact form page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   The render array containing the contact form.
   */
  public function contactPage(Request $request): array {
    $values = ['template' => 'contact_form_submission'];

    // Prepopulate the category if it has been passed as a query argument.
    if ($request->query->has('category')) {
      $category = $request->query->get('category');
      if ($this->isValidCategory($category)) {
        $values['field_contact_category'] = $category;
      }
    }

    // Prepopulate the page URL if it was passed as a query argument.
    if ($request->query->has('uri')) {
      $uri = $request->query->get('uri');
      if ($this->isValidUri($uri)) {
        $values['field_contact_url']['uri'] = 'internal:/' . ltrim($uri, '/');
      }
    }

    // Prepopulate the subject if it was passed as a query argument.
    if ($request->query->has('subject')) {
      $values['field_contact_subject'] = $request->query->get('subject');
    }

    $contact_message = $this->entityTypeManager()->getStorage('message')->create($values);
    $form = $this->entityFormBuilder()->getForm($contact_message);

    return $form;
  }

  /**
   * Checks whether the given category is valid for the contact form.
   *
   * @param string $category
   *   The category key, e.g. 'report'.
   *
   * @return bool
   *   TRUE if the category is valid.
   */
  protected function isValidCategory(string $category): bool {
    $contact_message = $this->entityTypeManager()->getStorage('message')->create([
      'template' => 'contact_form_submission',
    ]);
    $allowed_values = $contact_message->getFieldDefinition('field_contact_category')
      ->getSetting('allowed_values');

    return array_key_exists($category, $allowed_values);
  }

  /**
   * Checks whether the given URI is a valid Joinup URL.
   *
   * The URI must resolve to a routed, internal path on this site. External,
   * unroutable and otherwise invalid URIs are rejected.
   *
   * @param string $uri
   *   The URI, e.g. '/node/1'.
   *
   * @return bool
   *   TRUE if the URI is a valid Joinup URL.
   */
  protected function isValidUri(string $uri): bool {
    $path = '/' . ltrim($uri, '/');
    $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($path);

    return $url instanceof Url && !$url->isExternal();
  }

}
