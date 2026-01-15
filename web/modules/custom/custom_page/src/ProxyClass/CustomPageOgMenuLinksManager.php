<?php
// @codingStandardsIgnoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\custom_page\CustomPageOgMenuLinksManager' "modules/custom/custom_page/src".
 */

namespace Drupal\custom_page\ProxyClass {

  use Drupal\Core\DependencyInjection\DependencySerializationTrait;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
     * Provides a proxy class for \Drupal\custom_page\CustomPageOgMenuLinksManager.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class CustomPageOgMenuLinksManager implements \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface
    {
        use DependencySerializationTrait;

      /**
       * The real proxied service, after it was lazy loaded.
       *
       * @var \Drupal\custom_page\CustomPageOgMenuLinksManager
       */
      protected $service;

      public function __construct(
          protected readonly ContainerInterface $container,
          protected readonly string $drupalProxyOriginalServiceId,
        ) {}

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
        public function getChildren(\Drupal\node\NodeInterface $customPage, string $menuType) : array
        {
            return $this->lazyLoadItself()->getChildren($customPage, $menuType);
        }

        /**
         * {@inheritdoc}
         */
        public function addLink(\Drupal\node\NodeInterface $customPage, string $menuType, array $properties = []) : \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface
        {
            return $this->lazyLoadItself()->addLink($customPage, $menuType, $properties);
        }

        /**
         * {@inheritdoc}
         */
        public function moveLinks(\Drupal\node\NodeInterface $customPage, $groupId) : \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface
        {
            return $this->lazyLoadItself()->moveLinks($customPage, $groupId);
        }

      /**
       * {@inheritdoc}
       */
      public function updateLink(\Drupal\menu_link_content\MenuLinkContentInterface $menu_link) : void
      {}

        /**
         * {@inheritdoc}
         */
        public function deleteLinks(\Drupal\node\NodeInterface $customPage) : \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface
        {
            return $this->lazyLoadItself()->deleteLinks($customPage);
        }

        /**
         * {@inheritdoc}
         */
        public function getOgMenuInstanceByCustomPage(\Drupal\node\NodeInterface $customPage, string $menuType) : ?\Drupal\og_menu\OgMenuInstanceInterface
        {
            return $this->lazyLoadItself()->getOgMenuInstanceByCustomPage($customPage, $menuType);
        }

    }

}
