<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\EventSubscriber;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\joinup_core\Controller\ModalFormatterEntityViewController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for Joinup Modal Formatter.
 *
 * Adds the field view modal route callback used by "joinup_core_more_in_modal"
 * formatter.
 *
 * @see \Drupal\joinup_core\Plugin\Field\FieldFormatter\MoreInModalFormatter
 */
class ModalFormatterRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a new ModalFormatterRouteSubscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Adds modal field routes to content entities having canonical routes.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $definition) {
      if (!$definition instanceof ContentEntityTypeInterface) {
        continue;
      }

      // If the target entity (node, paragraphs, contact_message etc.) does
      // not have link templates, we don't add route.
      if (empty($definition->getLinkTemplates())) {
        continue;
      }

      $canonical = $definition->getLinkTemplate('canonical');
      $editForm = $definition->getLinkTemplate('edit') ?: $definition->getLinkTemplate('edit-form');

      // We will skip this entity even if the target entity does not have
      // canonical link template, or if canonical is equal to the edit link
      // template. For example media's canonical is their edit form by default.
      if (!$canonical || ($canonical === $editForm)) {
        continue;
      }

      // Get the canonical route definition.
      if (empty($canonicalRoute = $this->getRouteByPath($canonical, $collection))) {
        continue;
      }

      $defaults = array_diff_key(
        $canonicalRoute->getDefaults(),
        ['_title' => 1]
      );
      $defaults['_controller'] = ModalFormatterEntityViewController::class . '::buildViewModeResponse';
      $defaults['_title_callback'] = ModalFormatterEntityViewController::class . '::title';
      $defaults[$entityTypeId] = 'modal_formatter';

      $requirements = $canonicalRoute->getRequirements();
      $requirements['js'] = 'nojs|ajax';
      $requirements['_modal_formatter'] = 'TRUE';
      if (isset($requirements[$entityTypeId])) {
        $requirements['entity'] = $requirements[$entityTypeId];
        unset($requirements[$entityTypeId]);
      }

      $options = $canonicalRoute->getOptions();
      $options['parameters']['entity'] = $options['parameters'][$entityTypeId] ?? [
        'type' => "entity:$entityTypeId",
        'converter' => 'paramconverter.entity',
      ];
      if (isset($options['parameters'][$entityTypeId])) {
        $options['parameters'][$entityTypeId] = [
          'type' => "modal_formatter:$entityTypeId",
          'converter' => 'paramconverter.modal_formatter',
        ];
      }

      $fieldViewModalRoute = (new Route("/modal/$entityTypeId/{entity}/{viewMode}/{field}/{js}"))
        ->setDefaults($defaults)
        ->setRequirements($requirements)
        ->setOptions($options);
      $collection->add("entity.{$entityTypeId}.joinup_core.modal_formatter", $fieldViewModalRoute);
    }
  }

  /**
   * Returns the first matching route definition with the given path.
   *
   * @param string $path
   *   The path to search for.
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection to search in for the path.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   A Route definition which path is the given path, or NULL if there was no
   *   matching definition.
   */
  protected function getRouteByPath(string $path, RouteCollection $collection): ?Route {
    $matchingRoutes = array_filter(
      $collection->all(),
      function (Route $route) use ($path) {
        return $route->getPath() === $path;
      }
    );
    return reset($matchingRoutes) ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This needs to be called as late as possible.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -1000];
    return $events;
  }

}
