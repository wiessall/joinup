<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

/**
 * Bundle and field names this module reads or writes.
 *
 * In one place so that a renamed field surfaces as one failing test rather
 * than as a silent no-op scattered across services. Names under "ours" are
 * shipped in config/install; the rest belong to other modules or to site
 * config, and code reading them must degrade when they are absent.
 */
final class ContentModel {

  // Bundles.
  public const SOLUTION = 'solution';
  public const COLLECTION = 'collection';
  public const INNOVATION = 'atio_innovation';
  public const CLAIM = 'claims';
  public const CLAIM_PROPERTIES = 'claim_properties';
  public const CLAIM_VALUES = 'claim_values';

  // Solution and claim fields (import_ird_jsons, OG, site config).
  public const AUDIENCE = 'og_audience';
  public const CLAIMS = 'field_claims';
  public const CLAIMED_BY = 'field_claimed_by';
  public const CLAIM_PROPERTY = 'field_claim_property';
  public const CLAIM_REF_VALUE = 'field_claim_ref_value';
  public const CLAIM_LITERAL = 'field_claim_literal_value';
  public const CLAIMED_AT = 'field_claimed_at';
  public const RELATED_SOLUTIONS = 'related_solutions';

  // Ours: object of a sameInnovationAs claim, another solution.
  public const CLAIM_REF_NODE = 'field_claim_ref_node';

  // ATIO innovation fields (atio module).
  public const DERIVED_FROM = 'atio_derived_from';

  // Ours: on the claim vocabularies.
  public const ATIO_PROPERTY = 'field_atio_property';
  public const EXACT_MATCH = 'field_skos_exact_match';

}
