<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Event;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event class allowing subscribers to add group menu links.
 */
class GroupMenuLinksEvent extends Event {

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group to provide menu links for.
   * @param string $menuName
   *   The menu name.
   * @param string $menuType
   *   The menu type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected GroupInterface $group,
    protected string $menuName,
    protected string $menuType,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the group to provide menu links for.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The group to provide menu links for.
   */
  public function getGroup(): GroupInterface {
    return $this->group;
  }

  /**
   * Returns the menu name.
   *
   * @return string
   *   The menu name.
   */
  public function getMenuName(): string {
    return $this->menuName;
  }

  /**
   * Returns the menu type.
   *
   * @return string
   *   The menu type. Either 'navigation' or 'quick_links'.
   */
  public function getMenuType(): string {
    return $this->menuType;
  }

  /**
   * Adds a new menu link content to the group OG menu instance.
   *
   * @param \Drupal\Core\Url $url
   *   The menu link label.
   * @param string $label
   *   The menu link URL.
   * @param int $weight
   *   (optional) The menu weight. Defaults to 0.
   * @param array $values
   *   (optional) Additional values to be added to the menu link content entity
   *   creation.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException|\Drupal\Core\Entity\EntityStorageException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function addMenuLink(Url $url, string $label, int $weight = 0, array $values = []): self {
    $this->entityTypeManager->getStorage('menu_link_content')->create([
      'title' => $label,
      'menu_name' => $this->getMenuName(),
      'link' => [
        'uri' => $this->getUriString($url),
        'options' => $url->getOptions(),
      ],
      'weight' => $weight,
    ] + $values)->save();
    return $this;
  }

  /**
   * Returns the URL as URI string.
   *
   * We cannot use \Drupal\Core\Url::toUriString() for RDF entities because the
   * `rdf_entity` route parameter should be encoded. Most of the code is copied
   * from Url::toUriString().
   *
   * @param \Drupal\Core\Url $url
   *   The URL object.
   *
   * @return string
   *   The URI as string.
   *
   * @see \Drupal\Core\Url::toUriString()
   * @see \Drupal\sparql_entity_storage\UriEncoder::encodeUrl()
   */
  protected function getUriString(Url $url): string {
    if ($url->getOption('entity_type') !== 'rdf_entity' || !$url->getOption('entity') instanceof RdfInterface) {
      // Fallback to original method.
      return $url->toUriString();
    }

    // Canonical routes are getting a special treatment.
    if ($url->getRouteName() === 'entity.rdf_entity.canonical') {
      return 'entity:rdf_entity/' . UriEncoder::encodeUrl($url->getOption('entity')->id());
    }

    $uri = 'route:' . $url->getRouteName();
    if ($parameters = $url->getRouteParameters()) {
      if (isset($parameters['rdf_entity'])) {
        $parameters['rdf_entity'] = UriEncoder::encodeUrl($parameters['rdf_entity']);
      }
      $uri .= ';' . UrlHelper::buildQuery($parameters);
    }
    $query = !empty($this->options['query']) ? ('?' . UrlHelper::buildQuery($this->options['query'])) : '';
    $fragment = isset($this->options['fragment']) && strlen($this->options['fragment']) ? '#' . $this->options['fragment'] : '';
    return $uri . $query . $fragment;
  }

}
