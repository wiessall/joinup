<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Middleware;

use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Implementation of 'Accept' header based content negotiation.
 */
class JoinupAcceptHeaderMiddleware implements HttpKernelInterface {

  public function __construct(protected HttpKernelInterface $httpKernel) {}

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    if ($format = $this->getFormatFromRequest($request)) {
      $request->setRequestFormat($format);
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Determines the output format (eg. html, json, rdfxml,..).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string|null
   *   The format to use for this request. NULL will be returned for all non-RDF
   *   formats.
   */
  protected function getFormatFromRequest(Request $request): ?string {
    $acceptHeaderAsString = $request->headers->get('Accept') ?: 'text/html';
    // Use the 'Accept' header to determine format.
    $acceptHeader = AcceptHeader::fromString($acceptHeaderAsString);
    // AcceptHeader::first() is already sorting the list. The first is the
    // client most wanted mime.
    // @see \Symfony\Component\HttpFoundation\AcceptHeader::first()
    $mimeType = $acceptHeader->first()->getValue();

    /** @var \EasyRdf\Format $format */
    foreach (RdfSyncEncoder::getSupportedFormats() as $format) {
      if ($format->getDefaultMimeType() === $mimeType) {
        return $format->getName();
      }
    }

    // Return the format only for RDF formats. NULL means pass-through.
    return NULL;
  }

}
