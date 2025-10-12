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

The assessments have 3 states: `Draft`, `Under review`, and `Published`. There
are no revisions in the assessment entities. Evey assessment is final.
