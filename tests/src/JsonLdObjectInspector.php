<?php

declare(strict_types=1);

namespace Drupal\joinup;

use ML\IRI\IRI;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NodeInterface;
use ML\JsonLD\Value;

/**
 * A simple JSON-LD object inspector built for testing schema metadata.
 */
final class JsonLdObjectInspector {

  /**
   * Context of the JSON-LD object, used as vocabulary for property shorthands.
   *
   * @var string|\stdClass|null
   */
  protected string|\stdClass|null $context;

  /**
   * Storage of the discovered (and filtered) JSON-LD graph nodes.
   *
   * @var \ML\JsonLD\Node[]
   */
  protected array $jsonLdNodes = [];

  /**
   * Constructs a new JsonLdObjectInspector instance.
   *
   * @param \stdClass $jsonLd
   *   The JSON-LD object to inspect.
   * @param string|null $property
   *   A property to filter the discovered objects. Value can be specified in
   *   "$value". Optional.
   * @param string|null $value
   *   The expected value the specified property to not filter out a JSON-LD
   *   object. Optional. If "$property" is set and "$value" is NULL, then
   *   objects which have the specified property will be filtered out.
   * @param string|null $subProperty
   *   The sub-property to filter for. Works similar way as "$property".
   *
   * @throws \Exception
   */
  public function __construct(
    \stdClass $jsonLd,
    ?string $property = NULL,
    ?string $value = NULL,
    ?string $subProperty = NULL,
  ) {
    $this->context = $jsonLd->{'@context'} ?? NULL;
    $this->jsonLdNodes = JsonLD::getDocument($jsonLd)->getGraph()->getNodes();

    // If $property is defined, then $value might still be NULL, because it is a
    // valid use case: that way one can filter out objects which do have the
    // specified property.
    if (isset($property)) {
      $this->jsonLdNodes = array_filter(
        $this->jsonLdNodes,
        function (NodeInterface $node) use ($property, $value): bool {
          return $value === $this->getPropertyStringValue($node, $property);
        }
      );
    }

    if (isset($subProperty)) {
      $this->jsonLdNodes = array_filter(
        $this->jsonLdNodes,
        function (NodeInterface $node) use ($subProperty): bool {
          $matchingPropertyValue = $this->getPropertyValue($node, $subProperty);
          return isset($matchingPropertyValue);
        }
      );
    }

    if (empty($this->jsonLdNodes)) {
      throw new \Exception('No graph nodes left after filtering the JSON-LD object.');
    }
  }

  /**
   * Returns the number of discovered graph objects.
   *
   * @param string|null $type
   *   Type to filter the items to. Optional.
   *
   * @return int
   *   The number of graph objects.
   */
  public function count(?string $type = NULL): int {
    if (!isset($type)) {
      return count($this->jsonLdNodes);
    }
    $count = 0;
    foreach ($this->jsonLdNodes as $node) {
      if ($this->getPropertyStringValue($node, '@type') !== $type) {
        continue;
      }
      $count++;
    }
    return $count;
  }

  /**
   * Returns the value of a property from any discovered JSON-LD node.
   *
   * Returns the first non-null value, or NULL if none of the objects have any
   * value for the given property (and sub-property and / or delta if given).
   * The fourth parameter is passed by reference, and it will contain the ID of
   * the object the returned value belongs to.
   *
   * @param string $property
   *   A property to filter the discovered objects. Value can be specified in
   *   "$value". Optional.
   * @param string|null $subProperty
   *   The sub-property to filter for. Works similar way as "$property".
   * @param int|null $delta
   *   The delta to get the value from. Only works if the property's value is a
   *   list.
   * @param mixed $id
   *   If given, this variable will be set to the ID of the JSON-LD node object
   *   the returned value belongs to.
   *
   * @return string|string[]|null
   *   The value of a property from any discovered JSON-LD node.
   */
  public function getGraphNodeElementValue(
    string $property,
    ?string $subProperty = NULL,
    ?int $delta = NULL,
    mixed &$id = NULL,
  ): null|string|array {
    foreach ($this->jsonLdNodes as $node) {
      $id = $node->getId();

      // At first, get the sub property if given.
      $thingToCheck = $subProperty
        ? $this->getPropertyValue($node, $subProperty)
        : $node;
      // Second, get the item at the delta if given.
      $thingToCheck = isset($delta)
        ? $thingToCheck[$delta] ?? NULL
        : $thingToCheck;

      // Get the property value(s) as string.
      if (is_array($thingToCheck)) {
        $value = isset($thingToCheck[$property])
          ? $this->getPropertyStringValue($thingToCheck[$property])
          : NULL;
      }
      // The thing might be NULL because e.g. the sub property is missing, or
      // because fo that the delta is missing.
      elseif (!is_null($thingToCheck)) {
        $value = $this->getPropertyStringValue($thingToCheck, $property);
      }
      if (!isset($value)) {
        continue;
      }
      return $value;
    }
    return NULL;
  }

  /**
   * Returns the string value representation of a resource's property.
   *
   * @param \ML\JsonLD\NodeInterface|\ML\JsonLD\Value|\ML\IRI\IRI $resource
   *   The object to get the value from, or the actual value object.
   * @param string|null $property
   *   The property to get the value from the node. Might be skipped of the
   *   "$resource" is a value object or an IRI.
   *
   * @return string|null
   *   The property's string value, or NULL if the property does not exist on
   *   the node.
   */
  protected function getPropertyStringValue(
    NodeInterface|Value|IRI $resource,
    ?string $property = NULL,
  ): ?string {
    $propertyValue = $resource instanceof NodeInterface
      ? $this->getPropertyValue($resource, $property)
      : $resource->toJsonLd()?->{$property} ?? $resource;
    if (is_null($propertyValue)) {
      return NULL;
    }
    if ($propertyValue instanceof NodeInterface) {
      return $propertyValue->getId();
    }
    return $propertyValue instanceof Value
      ? $propertyValue->getValue()
      : (string) $propertyValue;
  }

  /**
   * Returns the value of a JSON-LD node property.
   *
   * @param \ML\JsonLD\NodeInterface $node
   *   The JSON-LD node object to get the value from.
   * @param string $property
   *   The property.
   *
   * @return \ML\JsonLD\NodeInterface|\ML\IRI\IRI|Value|string|null|
   *   The property value object: Node, Value, IRI, array if the value is a
   *   list, string if fetching the ID of the node, or NULL if the property does
   *   not exist on the node.
   */
  protected function getPropertyValue(
    NodeInterface $node,
    string $property,
  ): NodeInterface|IRI|Value|array|string|null {
    return match($property) {
      '@id' => $node->getId(),
      default => $node->getProperty($this->translateProperty($property)),
    };
  }

  /**
   * Translates property shorthands to fully qualified name, using the context.
   *
   * @param string $property
   *   The property name, or the property shorthand (if it is mapped in the
   *   JSON-LD object at the "@context" key).
   *
   * @return string
   *   The translated property name.
   */
  protected function translateProperty($property): string {
    if ($property[0] === '@') {
      return $property;
    }

    $mappedProperty = $this->context?->{$property}?->{'@id'} ?? $this?->context?->{$property} ?? NULL;
    if (is_string($mappedProperty)) {
      return $mappedProperty;
    }

    $baseUri = is_string($this->context ?? NULL)
        ? $this->context
        : NULL;

    // Base URI context must start with http in case of Drupal Schema Metadata
    // output.
    if ($baseUri === 'https://schema.org') {
      $baseUri = 'http://schema.org';
    }

    return implode('/', array_filter([$baseUri, $property]));
  }

}
