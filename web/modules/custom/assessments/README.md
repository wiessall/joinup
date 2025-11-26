# Assessments

This module provides functionality for creating and managing assessments within
Joinup.

## Model

The model can be found at [Assessments Model](https://semiceu.github.io/IOPA/releases/1.0.0/).
The rough representation of the classes is as follows:

* Assessment | The main assessment report entity
  * Public organisation | The entity that provides the assessment
  * Binding requirement | The legal basis for the assessment
    * Expression/Legal expression | The legal text or reference
    * Asset | The asset being assessed or is relevant to the assessment
    * Service | The affected services
    * Participation | The affected participant or stakeholder
      * Agent | Specific properties about the participant
    * Document | Supporting documents
    * Resource | Any other relevant resource
  * Statement | The assessment statements, judgment, score etc.
    * Document | Supporting documents
    * Service | The affected services

## Compromises/Changes

In Joinup, the structure of the assessment entity is as follows:

* Assessment | The main assessment report entity
  * Public organisation | The entity that provides the assessment
  * Expression/Legal expression | The legal text or reference
  * Service | The affected services
  * Binding requirement | The legal basis for the assessment
  * Participation | The affected participant or stakeholder
  * Statement | The assessment statements, judgment, score etc.
  * Asset | The asset being assessed or is relevant to the assessment
  * Binding requirement document | Supporting documents
  * Statement document | Supporting documents

In the above list, we consider the following:
* The `Expression/Legal expression` is a direct property of the `Assessment`,
not nested under `Binding requirement`. The provided expression will be
connected with every binding requirement of the assessment.
* The `Service` is a direct property of the `Assessment`, not nested under
`Binding requirement` or `Statement`. The provided services will be connected
with every binding requirement of the assessment.
* The `Binding requirement document` is a direct property of the `Assessment`,
not nested under `Binding requirement`. The provided documents will be
connected with every binding requirement of the assessment.
* The `Statement document` is a direct property of the `Assessment`, not nested
under `Statement`. The provided documents will be connected with every
statement of the assessment.
* The `Participation` is a direct property of the `Assessment`, not nested
under `Binding requirement`. The provided participations will be connected
with every binding requirement of the assessment.
* The `Asset` is a direct property of the `Assessment`, not nested under
`Binding requirement` or `Statement`. The provided assets will be connected
with every binding requirement and statement of the assessment.

The connection will take place when exporting the RDF data. This does not
affect how we present the data in Joinup.

## State machine

The assessments have 3 states: `Draft`, `Under review`, and `Published` and
`Rejected`. There are no revisions in the assessment entities. Evey assessment
is final.

## Current structure of fields

The current structure of fields in the assessment type is as follows:

Assessment (assessment):
* Affected party (affected_party). Entity reference to `participation`.
* Affected service (has_related_service). Entity reference to `public_service`.
* Asset (asset). Entity reference to `asset`.
* Binding requirement (binding_requirement). Entity reference to
`binding_requirement`.
* Binding requirements supporting documents (requirement_documents). Entity
reference to `file`.
* "Initiative, project or action concerned (containing the binding requirements
to be assessed)" (expression). Entity reference to `expression`.
* Name (name). Title field.
* Provided by (provided_by). Entity reference to `public_organisation`.
* Results in (results_in). Entity reference to `statement`.
* State (state). State field
* Statement supporting documents (statement_documents). Entity reference to
`file`.

Asset (asset):
* Landing page (landing_page). Link field. Uri is needed only.
* Title (name). Title field.

Binding requirement (binding_requirement):
* Short description (name). Title field.
* Documented in (documented_in). Link field. Uri is needed only.

Expression (expression):
* Description (description). Long text field.
* Expression type (expression_type). List (text) field. Allowed values:
`http://data.europa.eu/eli/ontology#LegalExpression` and
`http://purl.org/vocab/frbr/core#Expression`.
* If applicable, provide a link to the initiative, project or action concerned
(documented_in). Link field. Uri is needed only.
* Name or description (name). Title field.

Participation (participation):
* Country (country). List (text) field. Allowed values are the ISO 3166-1
alpha-2 country codes.
* Participation type (participant_type). List (text) field.

Public service (public_service):
* Name (name). Title field.
* Thematic area (thematic_area). List of values (text) field. Allowed values:
  * core_governance_and_administration
  * health_and_wellbeing
  * education_and_research
  * environment_and_energy
  * economy_and_employment
  * social_and_community_services
  * security_justice_and_emergency_services
  * transport_and_infrastructure
  * digital_and_innovation_services
  * international_and_regional_cooperation

Public organization (public_organisation):
* Type of public sector body (organization_type). List (text) field. Allowed values:
  * local_public_administration
  * regional_public_administration
  * national_public_administration
  * european_union_institution
  * european_union_agency
  * european_union_body
* Country (country). List (text) field. Allowed values are the ISO 3166-1
alpha-2 country codes.
* Name (name). Title field.

Statement (statement):
* Judgment (judgment). Long text field.
* Interpretation (interpretation). List (text) field. Allowed values:
  * -1
  * 0
  * 1
* Statement type (statement_type). List (text) field. Allowed values:
  * legal_interoperability
  * organisational_interoperability
  * semantic_interoperability
  * technical_interoperability
  * other_interoperability
