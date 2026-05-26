# Field → Graph translation

## Classification key

- **CORE** — property on the rdf_entity, stored in triplestore
- **TAXONOMY** — link to a SKOS concept (AGROVOC or local vocabulary)
- **RELATIONSHIP** — edge to another rdf_entity (org, person, source)
- **NARRATIVE** — statement entity with author/date/provenance (AI or human)
- **EVALUATION** — score from a framework, becomes evaluation entity
- **INTERNAL** — stays in MySQL, not in the graph (workflow, AI pipeline, display)
- **DROP** — redundant, unused, or absorbed by another mechanism
- **MIGRATE** — moves to a different entity type in the new model

---

## 1. Innovation (current: 80+ fields → target: ~12 core)

### CORE (properties on :Innovation rdf_entity)

| Current field | RDF property | Notes |
|---|---|---|
| title | dct:title | |
| body | dct:description | one long description, not five |
| field_shorter_description | dct:abstract | |
| field_country_origin | dct:spatial | URI to AGROVOC country |
| field_start_year | dct:created | |
| field_url_s | schema:url | |
| field_data_source | dct:source | ref to :SourceRecord |
| field_owner (first value) | dct:creator | ref to :Organization |
| field_if_grassroots | :isGrassroots | boolean |
| field_city | :city | optional, plain text |
| field_state | :state | optional, plain text |

### TAXONOMY (triples linking to SKOS concepts)

| Current field | Predicate | Vocabulary | Notes |
|---|---|---|---|
| field_innovation_type | :innovationType | ATIO types taxonomy | → SKOS in Virtuoso |
| field_use_cases | :useCase | ATIO use cases | → SKOS |
| field_thematic_area | :theme | FAO themes / AGROVOC | align to AGROVOC |
| field_challenges_addressed | :challengeAddressed | ATIO challenges | → SKOS |
| field_innovation_domain | :domain | STF domains | → SKOS |
| field_impact_sdgs | :sdgRelevance | UN SDGs | standard vocabulary |
| field_afs_stf_areas | :stfArea | STF areas | → SKOS |
| field_areas_of_expertise | :areaOfExpertise | STF | → SKOS |
| field_areas_of_need | :areaOfNeed | STF | → SKOS |
| field_innovative_features | :innovativeDimension | ATIO | → SKOS |
| field_environmental_impact | :environmentalImpact | ATIO | → SKOS |
| field_human_impact | :humanImpact | ATIO | → SKOS |
| field_inclusion_and_representati | :inclusionAspect | ATIO | → SKOS |
| field_investment_type | :investmentType | ATIO | → SKOS |
| field_languages | dct:language | ISO 639 | standard |
| field_ffs_type | :ffsType | FFS taxonomy | → SKOS |
| field_use_case_image | — | — | display concern, not data |

### RELATIONSHIP (edges to other rdf_entities)

| Current field | Predicate | Target type | Notes |
|---|---|---|---|
| field_owner | dct:creator | :Organization | primary |
| field_partners | :hasPartner | :Organization | |
| field_sponsors | :hasSponsor | :Organization | |
| field_grassroot_partner | :hasGrassrootPartner | :Organization | |
| field_collection | :inCollection | :Collection | if collections become entities |
| field_related_innovation_core | :broaderConcept | :InnovationCore | see note below |
| field_original_extracted_innovat | prov:wasDerivedFrom | :AIExtractedInnovation | provenance chain |
| field_countries_adoption | :adoptedIn | AGROVOC country URI | multi-value |
| field_region | :regionOfOrigin | AGROVOC/FAO region | |

### EVALUATION (become :Evaluation entities with framework ref)

| Current field | Framework | Notes |
|---|---|---|
| field_readiness_level | CGIAR Readiness (1-9) | score + framework ref |
| field_adoption_level | CGIAR Adoption (1-9) | score + framework ref |
| field_innovation_stage | ATIO stages (pre-emergent→obsolete) | score + framework ref |
| field_scaling_level_idia | IDIA scaling | score + framework ref |
| field_stf_development_status | STF dev status | score + framework ref |
| field_stf_level_of_recognition | STF recognition | score + framework ref |

Each becomes a triple pattern like:
```
:eval_001  a               :Evaluation ;
           :evaluates      :innovation_42 ;
           :framework      :cgiar_readiness ;
           :score          "4" ;
           :evaluatedBy    :source_digital_agri_hub ;  # or :human_curator_x
           :date           "2025-03-05" .
```

### NARRATIVE (become :Statement entities with provenance)

| Current field | Statement type | Notes |
|---|---|---|
| field_innovative_elements_narrat | :innovativeDimensionsNarrative | AI-generated, needs provenance |
| field_use_cases_description | :useCaseNarrative | AI-generated |
| field_readiness_description | :readinessNarrative | AI-generated |
| field_adoption_description | :adoptionNarrative | AI-generated |
| field_adoptability_dimensions | :adoptabilityNarrative | AI-generated |
| field_grassroots_innovation_narr | :grassrootsNarrative | AI-generated |

Each becomes:
```
:stmt_001  a               :Statement ;
           :about          :innovation_42 ;
           :statementType  :adoptabilityNarrative ;
           :text           "The system aims to increase..." ;
           :author         :ai_gemini ;
           :date           "2025-03-05" ;
           :confidence     :ai_generated .
```

Key change: these are **optional, additive, and clearly labeled** — not fields on the innovation.

### INTERNAL (stay in MySQL, not in graph)

| Current field | Reason |
|---|---|
| field_allow_ai_update | pipeline control |
| field_allow_ai_update_if_empty | pipeline control |
| field_allow_overwriting_by_origi | pipeline control |
| field_allow_overwr_if_empty | pipeline control |
| field_ai_stamp | pipeline metadata |
| field_atio_kb_source | pipeline flag |
| field_entity_creation_mode | pipeline metadata |
| field_record_status | editorial workflow |
| field_record_metadata | internal |
| field_record_long_metadata | internal |
| field_curation_notes | editorial |
| field_display_mode | display |
| field_description_markup | display |
| field_original_descr_markup | display |
| field_title_instructions | display |
| field_info_on_partial_editing | display |
| field_identifying_feature | internal dedup |
| field_bib_citation_of_pub | → moves to :ExtractedEntity of type publication |

### DROP or MERGE

| Current field | Reason |
|---|---|
| field_owner_text_field | redundant — use org entity ref |
| field_partner_text_field | redundant |
| field_sponsor_text_field | redundant |
| field_actual_users | merge with field_prospective_users → taxonomy link :userType |
| field_prospective_users | same |
| field_long_term_beneficiaries | same — all become :targetAudience taxonomy links |
| field_stf_organization_size | move to :Organization entity |
| field_stf_organization_type | move to :Organization entity |
| field_award_year | niche, rarely populated — becomes a statement if needed |
| field_start_date | redundant with field_start_year |
| field_information_resource_date | belongs on :SourceRecord, not on innovation |

---

## 2. Organization (current: 16 fields → target: ~8 core)

### CORE

| Current field | RDF property | Notes |
|---|---|---|
| title | foaf:name | |
| field_acronym | :acronym | |
| field_country | dct:spatial | AGROVOC URI |
| field_link | foaf:homepage | |
| field_organization_type | :organizationType | → SKOS taxonomy |
| field_organization_size | :organizationSize | → SKOS taxonomy |
| field_region | :region | AGROVOC/FAO region |
| field_data_source | dct:source | provenance |

### RELATIONSHIP

| Current field | Predicate | Notes |
|---|---|---|
| field_related_innovations | :associatedWith | inverse of innovation's :hasPartner etc. |
| field_collection | :inCollection | |

### INTERNAL

| Current field | Reason |
|---|---|
| field_asti_agency | ASTI-specific flag |
| field_asti_focal_point | ASTI-specific flag |
| field_duplicate | dedup workflow |
| field_quality | editorial workflow |
| field_org_type_in_source | provenance metadata |
| field_sti_legacy_terms | migration artifact |

---

## 3. Learning resource (current: 2 fields → almost nothing)

### Current state
| Current field | Type |
|---|---|
| field_collection | entity_reference |
| field_link | link |

This is barely a content type. In the graph model it becomes either:

**Option A**: a :LearningResource entity with:
- dct:title
- schema:url (from field_link)
- :inCollection
- dct:subject → taxonomy links
- :relatedInnovation → edge to innovations

**Option B**: absorb into :ExtractedEntity with type "learning_resource" — it's just another thing pulled from a source, like publications and patents already are.

I'd go with Option B for now. Learning resources are corollary entities like publications. They don't need their own class until they have enough properties to justify one.

---

## Summary: field count reduction

| Entity | Current fields | Core RDF properties | Taxonomy links | Relationships | Evaluations | Narratives | Internal/drop |
|---|---|---|---|---|---|---|---|
| Innovation | 80+ | ~11 | ~16 | ~9 | ~6 | ~6 | ~30+ |
| Organization | 16 | ~8 | 2 (via core) | 2 | 0 | 0 | ~6 |
| Learning resource | 2 | absorbed into ExtractedEntity | — | — | — | — | — |

The innovation entity goes from 80+ Drupal fields to **11 core properties on the rdf_entity**.
Everything else is either a triple to a vocabulary, an edge to another entity, a separate evaluation entity, a separate narrative statement, or internal workflow data that stays in MySQL.

---

## Next: what about innovation_core?

`innovation_core` in the current model appears to be a "class" or "concept" node — a broader grouping that individual innovations are instances of (like "drip irrigation" as a concept vs. specific drip irrigation products). In the graph this becomes natural:

```
:drip_irrigation  a                   :InnovationConcept ;
                  rdfs:label          "Drip irrigation" ;
                  :innovationType     :technological_mechanization ;
                  :useCase            :water_use_optimization .

:innovation_42    a                   :Innovation ;
                  :instanceOf         :drip_irrigation ;
                  dct:title           "Habibi Garden Solar Drip System" .
```

This is the granularity problem the design doc wrestles with (branded products vs. general solutions). The graph handles it natively — concepts and instances are different classes linked by `:instanceOf`. No forced choice between "do we list the brand or the concept."

---

## Next: what about innovation_extracted_from_origin and source_record?

These stay in MySQL as part of the ingestion pipeline. They're internal staging entities — the graph only sees the published innovation after triage. The provenance chain is preserved as:

```
:innovation_42  prov:wasDerivedFrom  :ai_extraction_42 .
:ai_extraction_42  prov:wasDerivedFrom  :source_record_4523 .
:source_record_4523  dct:source  :data_source_digital_agri_hub .
```

The source_record and AI-extracted entities don't need to be in the triplestore. They're workflow artifacts. Only the published innovation and its provenance links go into the graph.
