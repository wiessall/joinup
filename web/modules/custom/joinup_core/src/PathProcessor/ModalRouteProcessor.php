<?php

declare(strict_types=1);

namespace Drupal\joinup_core\PathProcessor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Path processor which construct and reveals nice field view paths.
 */
class ModalRouteProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * Separator to be used in the human friendly path.
   *
   * This is used in the path between the entity's canonical path and the field
   * view route params (source view mode, field name).
   *
   * @const string
   */
  const SEPARATOR = 'view-field';

  /**
   * Path alias module's path processor.
   */
  protected ?OutboundPathProcessorInterface $aliasPathProcessor;

  /**
   * The router service.
   *
   * We cannot inject it because circular reference.
   */
  protected RouterInterface $router;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Injects Path alias module's path processor.
   *
   * @param \Drupal\Core\PathProcessor\OutboundPathProcessorInterface $aliasPathProcessor
   *   Path alias module's path processor.
   */
  public function setAliasProcessor(OutboundPathProcessorInterface $aliasPathProcessor): void {
    $this->aliasPathProcessor = $aliasPathProcessor;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if (!$this->appliesToInboundPath($path)) {
      return $path;
    }
    [$hostAlias, $rest] = explode('/' . self::SEPARATOR . '/', $path);

    // Get back raw entity_type_id and entity_id from the canonical path.
    // First, get the route object.
    $hostAliasWithBase = $request->getBasePath() . $hostAlias;
    $matchedRoute = $this->getRouter()->match($hostAlias);
    $entityCandidates = array_filter(
      $matchedRoute,
      function ($default) use ($hostAliasWithBase) {
        return $default instanceof EntityInterface &&
          $default->toUrl()->toString() === $hostAliasWithBase;
      }
    );
    $entity = reset($entityCandidates);
    if (!$entity instanceof EntityInterface) {
      return $path;
    }
    $entityTypeId = $entity->getEntityTypeId();
    $entityId = $entity->id();

    return implode(
      '/',
      [
        '',
        'modal',
        $entityTypeId,
        $entityId,
        $rest,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (!$this->appliesToOutboundPath($path)) {
      return $path;
    }
    [, $entityTypeId, $entityId, $rest] = array_values(
      array_filter(
        explode('/', $path, 5),
        fn (string $piece) => $piece !== '',
      )
    );

    // Nothing guarantees that every entity canonical path is in form
    // '<entity_type_id>/<entity_id>', so we use the entity itself to generate
    // its canonical path.
    $entityCanonical = $this->entityTypeManager->getStorage($entityTypeId)
      ->load($entityId)
      ->toUrl()
      ->toString();
    $entityCanonicalWithoutBase = preg_replace(
      '#^' . preg_quote($request->getBasePath()) . '#',
      '',
      $entityCanonical
    );
    $outboundCanonical = isset($this->aliasPathProcessor)
      ? $this->aliasPathProcessor->processOutbound($entityCanonicalWithoutBase, $options, $request)
      : $entityCanonicalWithoutBase;

    return implode(
      '/',
      [
        $outboundCanonical,
        'view-field',
        $rest,
      ]
    );
  }

  /**
   * Determines whether an inbound path is a field-in-modal path or not.
   *
   * @param string $path
   *   The inbound path.
   *
   * @return bool
   *   Whether an inbound path is a field-in-modal path or not.
   */
  protected function appliesToInboundPath(string $path): bool {
    $pathParts = array_filter(
      explode('/', $path),
      fn (string $piece) => $piece !== '',
    );

    return array_search(static::SEPARATOR, $pathParts) !== FALSE;
  }

  /**
   * Determines whether an outbound path is a field-in-modal path or not.
   *
   * @param string $path
   *   The outbound path.
   *
   * @return bool
   *   Whether an outbound path is a field-in-modal path or not.
   */
  protected function appliesToOutboundPath(string $path): bool {
    $pathParts = array_filter(
      explode('/', $path),
      fn (string $piece) => $piece !== '',
    );

    return count($pathParts) === 5 && reset($pathParts) === 'modal';
  }

  /**
   * Returns the router service.
   */
  protected function getRouter(): RouterInterface {
    if (!isset($this->router)) {
      // The service is not injected to avoid container circular dependencies.
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      return $this->router = \Drupal::service('router.no_access_checks');
    }
    return $this->router;
  }

}
