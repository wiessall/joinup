import { useState } from "react";

const data = {
  Innovation: {
    color: "#2563eb",
    subclasses: ["GrassrootsInnovation", "DigitalVillageSolution"],
    core: [
      { field: "dct:title", from: "title", type: "text" },
      { field: "dct:description", from: "body", type: "text" },
      { field: "dct:abstract", from: "field_shorter_description", type: "text" },
      { field: "dct:spatial", from: "field_country_origin", type: "AGROVOC URI" },
      { field: "dct:created", from: "field_start_year", type: "year" },
      { field: "schema:url", from: "field_url_s", type: "link" },
      { field: "dct:source", from: "field_data_source", type: "ref → SourceRecord" },
      { field: "dct:creator", from: "field_owner", type: "ref → Organization" },
      { field: ":isGrassroots", from: "field_if_grassroots", type: "boolean → becomes subclass" },
      { field: ":city", from: "field_city", type: "text" },
      { field: ":state", from: "field_state", type: "text" },
    ],
    taxonomy: [
      { field: ":innovationType", from: "field_innovation_type", vocab: "ATIO types" },
      { field: ":useCase", from: "field_use_cases", vocab: "ATIO use cases" },
      { field: ":theme", from: "field_thematic_area", vocab: "FAO themes / AGROVOC" },
      { field: ":challengeAddressed", from: "field_challenges_addressed", vocab: "ATIO challenges" },
      { field: ":domain", from: "field_innovation_domain", vocab: "STF domains" },
      { field: ":sdgRelevance", from: "field_impact_sdgs", vocab: "UN SDGs" },
      { field: ":stfArea", from: "field_afs_stf_areas", vocab: "STF areas" },
      { field: ":innovativeDimension", from: "field_innovative_features", vocab: "ATIO" },
      { field: ":environmentalImpact", from: "field_environmental_impact", vocab: "ATIO" },
      { field: ":humanImpact", from: "field_human_impact", vocab: "ATIO" },
      { field: ":investmentType", from: "field_investment_type", vocab: "ATIO" },
      { field: "dct:language", from: "field_languages", vocab: "ISO 639" },
      { field: ":targetAudience", from: "field_actual_users + field_prospective_users + field_long_term_beneficiaries", vocab: "ATIO actor types" },
    ],
    relationships: [
      { field: ":hasPartner", from: "field_partners", target: "Organization" },
      { field: ":hasSponsor", from: "field_sponsors", target: "Organization" },
      { field: ":hasGrassrootPartner", from: "field_grassroot_partner", target: "Organization" },
      { field: ":inCollection", from: "field_collection", target: "Collection" },
      { field: ":instanceOf", from: "field_related_innovation_core", target: "InnovationConcept" },
      { field: "prov:wasDerivedFrom", from: "field_original_extracted_innovat", target: "AIExtractedInnovation" },
      { field: ":adoptedIn", from: "field_countries_adoption", target: "AGROVOC country" },
      { field: ":regionOfOrigin", from: "field_region", target: "AGROVOC region" },
    ],
    evaluations: [
      { field: "Readiness level", from: "field_readiness_level", framework: "CGIAR Readiness (1-9)" },
      { field: "Adoption level", from: "field_adoption_level", framework: "CGIAR Adoption (1-9)" },
      { field: "Innovation stage", from: "field_innovation_stage", framework: "ATIO stages" },
      { field: "Scaling level", from: "field_scaling_level_idia", framework: "IDIA scaling" },
      { field: "Dev status", from: "field_stf_development_status", framework: "STF" },
      { field: "Recognition", from: "field_stf_level_of_recognition", framework: "STF" },
    ],
    narratives: [
      { field: "Innovative dimensions", from: "field_innovative_elements_narrat" },
      { field: "Use cases", from: "field_use_cases_description" },
      { field: "Readiness", from: "field_readiness_description" },
      { field: "Adoption", from: "field_adoption_description" },
      { field: "Adoptability", from: "field_adoptability_dimensions" },
      { field: "Grassroots", from: "field_grassroots_innovation_narr" },
    ],
    internal: [
      "field_allow_ai_update", "field_allow_ai_update_if_empty", "field_allow_overwriting_by_origi",
      "field_allow_overwr_if_empty", "field_ai_stamp", "field_atio_kb_source",
      "field_entity_creation_mode", "field_record_status", "field_record_metadata",
      "field_record_long_metadata", "field_curation_notes", "field_display_mode",
      "field_description_markup", "field_original_descr_markup", "field_title_instructions",
      "field_info_on_partial_editing", "field_identifying_feature", "field_bib_citation_of_pub",
    ],
    drop: [
      "field_owner_text_field", "field_partner_text_field", "field_sponsor_text_field",
      "field_stf_organization_size → move to Org", "field_stf_organization_type → move to Org",
      "field_award_year", "field_start_date (redundant)", "field_information_resource_date → SourceRecord",
    ],
  },
  Organization: {
    color: "#0d9488",
    subclasses: [],
    core: [
      { field: "foaf:name", from: "title", type: "text" },
      { field: ":acronym", from: "field_acronym", type: "text" },
      { field: "dct:spatial", from: "field_country", type: "AGROVOC URI" },
      { field: "foaf:homepage", from: "field_link", type: "link" },
      { field: ":organizationType", from: "field_organization_type", type: "SKOS taxonomy" },
      { field: ":organizationSize", from: "field_organization_size", type: "SKOS taxonomy" },
      { field: ":region", from: "field_region", type: "AGROVOC region" },
      { field: "dct:source", from: "field_data_source", type: "provenance" },
    ],
    taxonomy: [],
    relationships: [
      { field: ":associatedWith", from: "field_related_innovations", target: "Innovation" },
      { field: ":inCollection", from: "field_collection", target: "Collection" },
    ],
    evaluations: [],
    narratives: [],
    internal: [
      "field_asti_agency", "field_asti_focal_point", "field_duplicate",
      "field_quality", "field_org_type_in_source", "field_sti_legacy_terms",
    ],
    drop: [],
  },
  InnovationConcept: {
    color: "#7c3aed",
    subclasses: [],
    core: [
      { field: "rdfs:label", from: "title", type: "text" },
      { field: ":broaderConcept", from: "field_broader", type: "ref → InnovationConcept" },
      { field: "dct:description", from: "field_examples", type: "text" },
    ],
    taxonomy: [
      { field: ":innovationType", from: "field_innovation_type", vocab: "ATIO types" },
      { field: ":useCase", from: "field_use_cases", vocab: "ATIO use cases" },
    ],
    relationships: [
      { field: ":hasInstance", from: "field_related_innovation_records", target: "Innovation" },
    ],
    evaluations: [],
    narratives: [],
    internal: ["field_quality", "field_ai_disclaimer", "field_block"],
    drop: [],
  },
};

const categoryMeta = {
  core: { label: "Core properties", color: "#2563eb", bg: "#eff6ff", desc: "On the rdf_entity in Virtuoso" },
  taxonomy: { label: "Taxonomy links", color: "#0d9488", bg: "#f0fdfa", desc: "Triples to SKOS concepts" },
  relationships: { label: "Relationships", color: "#7c3aed", bg: "#f5f3ff", desc: "Edges to other entities" },
  evaluations: { label: "Evaluations", color: "#ea580c", bg: "#fff7ed", desc: "Separate evaluation entities" },
  narratives: { label: "Narratives", color: "#ca8a04", bg: "#fefce8", desc: "Statement entities with provenance" },
  internal: { label: "Internal (MySQL)", color: "#6b7280", bg: "#f9fafb", desc: "Stays in Drupal, not in graph" },
  drop: { label: "Drop / merge", color: "#dc2626", bg: "#fef2f2", desc: "Redundant or moved elsewhere" },
};

function CategorySection({ category, items, entityColor }) {
  const [open, setOpen] = useState(false);
  const meta = categoryMeta[category];
  if (!items || items.length === 0) return null;

  return (
    <div style={{ marginBottom: 4 }}>
      <button
        onClick={() => setOpen(!open)}
        style={{
          display: "flex", alignItems: "center", gap: 8, width: "100%",
          padding: "6px 10px", border: "none", borderRadius: 6,
          background: open ? meta.bg : "transparent",
          cursor: "pointer", fontSize: 13, textAlign: "left",
          color: meta.color, fontWeight: 500,
          transition: "background 0.15s",
        }}
        onMouseEnter={e => e.currentTarget.style.background = meta.bg}
        onMouseLeave={e => { if (!open) e.currentTarget.style.background = "transparent"; }}
      >
        <span style={{ fontSize: 11, opacity: 0.5 }}>{open ? "▼" : "▶"}</span>
        <span>{meta.label}</span>
        <span style={{ fontSize: 11, color: "#9ca3af", fontWeight: 400, marginLeft: "auto" }}>
          {items.length} {meta.desc && `· ${meta.desc}`}
        </span>
      </button>
      {open && (
        <div style={{ padding: "4px 0 4px 24px" }}>
          {items.map((item, i) => (
            <div
              key={i}
              style={{
                fontSize: 12, padding: "4px 8px", borderRadius: 4, marginBottom: 2,
                display: "flex", gap: 8, alignItems: "baseline",
                lineHeight: 1.5,
              }}
              title={typeof item === "string" ? item : `${item.from || ""} → ${item.field || ""}`}
            >
              {typeof item === "string" ? (
                <span style={{ color: "#6b7280", fontFamily: "monospace", fontSize: 11 }}>{item}</span>
              ) : (
                <>
                  <code style={{
                    color: meta.color, fontSize: 11, fontWeight: 600,
                    minWidth: 160, flexShrink: 0,
                  }}>
                    {item.field}
                  </code>
                  <span style={{ color: "#9ca3af", fontSize: 11 }}>←</span>
                  <code style={{ color: "#6b7280", fontSize: 11 }}>{item.from}</code>
                  {(item.type || item.vocab || item.framework || item.target) && (
                    <span style={{ color: "#9ca3af", fontSize: 11, marginLeft: "auto", flexShrink: 0 }}>
                      {item.type || item.vocab || item.framework || `→ ${item.target}`}
                    </span>
                  )}
                </>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function EntityCard({ name, entity }) {
  const [open, setOpen] = useState(name === "Innovation");
  const totalFields = entity.core.length + entity.taxonomy.length +
    entity.relationships.length + entity.evaluations.length +
    entity.narratives.length + entity.internal.length + entity.drop.length;

  return (
    <div style={{
      border: `1.5px solid ${entity.color}22`,
      borderRadius: 10, marginBottom: 12, overflow: "hidden",
      background: "white",
    }}>
      <button
        onClick={() => setOpen(!open)}
        style={{
          display: "flex", alignItems: "center", gap: 10, width: "100%",
          padding: "12px 14px", border: "none", cursor: "pointer",
          background: `${entity.color}08`, textAlign: "left",
        }}
      >
        <span style={{
          width: 10, height: 10, borderRadius: "50%",
          background: entity.color, flexShrink: 0,
        }} />
        <span style={{ fontSize: 15, fontWeight: 600, color: "#1e293b" }}>{name}</span>
        {entity.subclasses.length > 0 && (
          <span style={{ fontSize: 11, color: "#9ca3af" }}>
            ↳ {entity.subclasses.join(", ")}
          </span>
        )}
        <span style={{ fontSize: 11, color: "#9ca3af", marginLeft: "auto" }}>
          {entity.core.length} core · {totalFields} total
        </span>
        <span style={{ fontSize: 11, opacity: 0.4 }}>{open ? "▼" : "▶"}</span>
      </button>
      {open && (
        <div style={{ padding: "4px 8px 8px" }}>
          {["core", "taxonomy", "relationships", "evaluations", "narratives", "internal", "drop"].map(cat => (
            <CategorySection key={cat} category={cat} items={entity[cat]} entityColor={entity.color} />
          ))}
        </div>
      )}
    </div>
  );
}

export default function App() {
  return (
    <div style={{ fontFamily: "'IBM Plex Sans', system-ui, sans-serif", maxWidth: 720, margin: "0 auto", padding: "20px 12px" }}>
      <h2 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4, color: "#1e293b" }}>
        Field → graph translation
      </h2>
      <p style={{ fontSize: 12, color: "#6b7280", marginBottom: 16 }}>
        Click entity to expand · click category to see field mappings · hover for Drupal source field
      </p>
      {Object.entries(data).map(([name, entity]) => (
        <EntityCard key={name} name={name} entity={entity} />
      ))}
      <div style={{ fontSize: 11, color: "#9ca3af", marginTop: 16, padding: "8px 0", borderTop: "1px solid #f1f5f9" }}>
        source_record and innovation_extracted_from_origin stay in MySQL — pipeline artifacts, not graph entities.
        Learning resources absorbed into ExtractedEntity.
      </div>
    </div>
  );
}
