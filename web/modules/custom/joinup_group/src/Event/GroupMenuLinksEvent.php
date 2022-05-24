<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event class allowing subscribers to add group menu links.
 */
class GroupMenuLinksEvent extends Event {

  /**
   * The group to provide menu links for.
   *
   * @var \Drupal\rdf_entity\RdfInterface
   */
  protected $group;

  /**
   * The menu name.
   *
   * @var string
   */
  protected $menuName;

  /**
   * The menu link content entity storage.
   *
   * @var \Drupal\menu_link_content\MenuLinkContentStorageInterface
   */
  protected $menuLinkContentStorage;

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\rdf_entity\RdfInterface $group
   *   The group to provide menu links for.
   * @param string $menu_name
   *   The menu name.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function __construct(RdfInterface $group, string $menu_name, EntityTypeManagerInterface $entity_type_manager) {
    $this->group = $group;
    $this->menuName = $menu_name;
    $this->menuLinkContentStorage = $entity_type_manager->getStorage('menu_link_content');
  }

  /**
   * Returns the group to provide menu links for.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   The group to provide menu links for.
   */
  public function getGroup(): RdfInterface {
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the menu link entity could not be saved.
   */
  public function addMenuLink(Url $url, string $label, int $weight = 0, array $values = []): self {
    $this->menuLinkContentStorage->create([
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
   *   The URI string.
   *
   * @see \Drupal\Core\Url::toUriString()
   * @see \Drupal\sparql_entity_storage\UriEncoder::encodeUrl()
   */
  protected function getUriString(Url $url): string {
    if ($url->getOption('entity_type') === 'rdf_entity' && $url->getOption('entity') instanceof RdfInterface) {
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
    return $url->toUriString();
  }

}
