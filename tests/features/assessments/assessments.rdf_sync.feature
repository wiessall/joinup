@api
Feature: RDF Sync integration for assessments.

  Scenario: Test that an entire chain of entities is exported properly.
    Given "participation" assessment:
      | uri                                                                                     | name          | stakeholder_country | participant_type         |
      | https://joinup.ddev.local/assessment/participation/f5379633-58af-49bd-ab71-22d8ab77d59a | Stakeholder 1 | Belgium             | local_public_sector_body |
    And "public_service" assessment:
      | uri                                                                                      | name             | identifier | thematic_area                      |
      | https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | Public Service 1 | PS-001     | core_governance_and_administration |
    And "public_organisation" assessment:
      | uri                                                                                           | name                  | organisation_type          | country |
      | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 | Public Organisation 1 | european_union_institution | Belgium |
    And "expression" assessment:
      | uri                                                                                  | name         | description   | documented_in       |
      | https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47 | Expression 1 | Description 1 | https://example.com |
    And the following "binding_requirement" paragraph:
      | uri            | https://joinup.ddev.local/paragraph/binding-requirement/a478fe88-c240-4769-8952-7d129f4ef449 |
      | id             | 999                                                                                          |
      | description    | BR Description 1                                                                             |
      | expression     | Expression 1                                                                                 |
      | affects        | Public Service 1                                                                             |
      | affected_party | Stakeholder 1                                                                                |
    And the following assessment statements:
      | uri                                                                                       | id  | statement_type | interpretation | judgement |
      | https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361 | 999 | legal          | positive       | Compliant |
    And "report" assessment:
      | uri                                                                              | name     | binding_requirement | results_in | provider              |
      | https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | Report 1 | 999                 | 999        | Public Organisation 1 |
    And I am logged in as a moderator

    When I visit the export page of the entity having "https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae" as RDF resource in "turtle" format
    Then the response should contain the following lines of text:
      # Prefixes used in the export.
      | @prefix cav: <http://data.europa.eu/2sa/cav#> .                                                                                                                                                                  |
      | @prefix cv: <http://data.europa.eu/m8g/> .                                                                                                                                                                       |
      | @prefix skos: <http://www.w3.org/2004/02/skos/core#> .                                                                                                                                                           |
      | @prefix dc: <http://purl.org/dc/terms/> .                                                                                                                                                                        |
      | @prefix org: <http://www.w3.org/ns/org#> .                                                                                                                                                                       |
      | @prefix iopa: <http://data.europa.eu/2qy/iopa#> .                                                                                                                                                                |
      | @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .                                                                                                                                                               |
      # Assessment Report is exported.
      | <https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae>                                                                                                                               |
      | a cav:Assessment ;                                                                                                                                                                                               |
      | cav:assesses <https://joinup.ddev.local/paragraph/binding-requirement/a478fe88-c240-4769-8952-7d129f4ef449> ;                                                                                                    |
      | cav:resultsIn <https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361> ;                                                                                                      |
      | cv:isProvidedBy <https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018> .                                                                                                |
      | <https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018>                                                                                                                  |
      # Public Organisations (providers) are exported.
      | a cv:PublicOrganisation ;                                                                                                                                                                                        |
      | skos:prefLabel "Public Organisation 1"@en ;                                                                                                                                                                      |
      | dc:spatial <https://joinup.ddev.local/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66> ;                                                                                                              |
      | org:classification <https://joinup.ddev.local/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25> .                                                                                         |
      # Binding Requirements are exported.
      | <https://joinup.ddev.local/paragraph/binding-requirement/a478fe88-c240-4769-8952-7d129f4ef449>                                                                                                                   |
      | a iopa:BindingRequirement ;                                                                                                                                                                                      |
      | cv:hasParticipation <https://joinup.ddev.local/assessment/participation/f5379633-58af-49bd-ab71-22d8ab77d59a> ;                                                                                                  |
      | iopa:affects <https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af> ;                                                                                                        |
      | cav:valueDescription "BR Description 1"@en ;                                                                                                                                                                     |
      | dc:isPartOf <https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47> .                                                                                                             |
      # Initiatives (Expressions) are exported.
      | <https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47>                                                                                                                           |
      | a <http://purl.org/vocab/frbr/core#Expression> ;                                                                                                                                                                 |
      | dc:title "Expression 1"@en ;                                                                                                                                                                                     |
      | dc:description "Description 1" ;                                                                                                                                                                                 |
      | cav:documentedIn <https://example.com> .                                                                                                                                                                         |
      # Affected services are exported.
      | <https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af>                                                                                                                       |
      | a <http://purl.org/vocab/cpsv#PublicService> ;                                                                                                                                                                   |
      | dc:title "Public Service 1"@en ;                                                                                                                                                                                 |
      | cv:thematicArea <https://joinup.ddev.local/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b> .                                                                                      |
      # Participation is exported.
      | <https://joinup.ddev.local/assessment/participation/f5379633-58af-49bd-ab71-22d8ab77d59a>                                                                                                                        |
      | a cv:Participation ;                                                                                                                                                                                             |
      | dc:type <https://joinup.ddev.local/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336> .                                                                                                 |
      | <https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361>                                                                                                                      |
      | a cav:Statement ;                                                                                                                                                                                                |
      | cav:judgement "Compliant" ;                                                                                                                                                                                      |
      | cav:type <https://joinup.ddev.local/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47> .                                                                                                  |
      # Terms are exported alongside the main entities.
      | <https://joinup.ddev.local/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66>                                                                                                                           |
      | skos:inScheme <http://publications.europa.eu/resource/authority/country>, <http://publications.europa.eu/resource/authority/country/0001>, <http://publications.europa.eu/resource/authority/country/0005> ;     |
      | skos:prefLabel "Belgium"@en ;                                                                                                                                                                                    |
      | skos:definition ""@en ;                                                                                                                                                                                          |
      | a dc:Location, skos:Concept ;                                                                                                                                                                                    |
      | skos:topConceptOf <http://publications.europa.eu/resource/authority/country>, <http://publications.europa.eu/resource/authority/country/0001>, <http://publications.europa.eu/resource/authority/country/0005> . |
      | <https://joinup.ddev.local/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25>                                                                                                              |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "European Union institution"@en ;                                                                                                                                                                 |
      | skos:altLabel "european_union_institution"^^xsd:string ;                                                                                                                                                         |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <https://joinup.ddev.local/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b>                                                                                                        |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "Core governance and administration"@en ;                                                                                                                                                         |
      | skos:altLabel "core_governance_and_administration"^^xsd:string ;                                                                                                                                                 |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <https://joinup.ddev.local/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336>                                                                                                           |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "Local public sector body"@en ;                                                                                                                                                                   |
      | skos:altLabel "local_public_sector_body"^^xsd:string ;                                                                                                                                                           |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <https://joinup.ddev.local/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47>                                                                                                             |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "Legal"@en ;                                                                                                                                                                                      |
      | skos:altLabel "legal"^^xsd:string ;                                                                                                                                                                              |
      | a skos:Concept .                                                                                                                                                                                                 |

    # Verify the data of the exported entities is correct.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                                   | predicate                                       | object                                                                                             |
      | https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361 | http://data.europa.eu/2sa/cav#judgement         | Compliant                                                                                          |
      | https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361 | http://data.europa.eu/2sa/cav#type              | https://joinup.ddev.local/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47 |
      | https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/2sa/cav#Statement                                                            |

    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                              | predicate                                       | object                                     |
      | https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47 | http://data.europa.eu/2sa/cav#documentedIn      | https://example.com                        |
      | https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47 | http://purl.org/dc/terms/description            | Description 1                              |
      | https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47 | http://purl.org/dc/terms/title                  | Expression 1                               |
      | https://joinup.ddev.local/assessment/expression/048caacf-52d6-4215-a867-a485ff4baf47 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/vocab/frbr/core#Expression |

    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                                 | predicate                                       | object                                                                                               |
      | https://joinup.ddev.local/assessment/participation/f5379633-58af-49bd-ab71-22d8ab77d59a | http://purl.org/dc/terms/type                   | https://joinup.ddev.local/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336 |
      | https://joinup.ddev.local/assessment/participation/f5379633-58af-49bd-ab71-22d8ab77d59a | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/m8g/Participation                                                              |

    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                                       | predicate                                       | object                                                                                            |
      | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 | http://purl.org/dc/terms/spatial                | https://joinup.ddev.local/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66              |
      | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/m8g/PublicOrganisation                                                      |
      | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/2004/02/skos/core#prefLabel   | Public Organisation 1                                                                             |
      | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/ns/org#classification         | https://joinup.ddev.local/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25 |

    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                                  | predicate                                       | object                                                                                                  |
      | https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://data.europa.eu/m8g/thematicArea          | https://joinup.ddev.local/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b |
      | https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://purl.org/dc/terms/title                  | Public Service 1                                                                                        |
      | https://joinup.ddev.local/assessment/public-service/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/vocab/cpsv#PublicService                                                                |

    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                                                                          | predicate                                       | object                                                                                        |
      | https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/2sa/cav#assesses          | https://joinup.ddev.local/paragraph/binding-requirement/a478fe88-c240-4769-8952-7d129f4ef449  |
      | https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/2sa/cav#resultsIn         | https://joinup.ddev.local/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361     |
      | https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/m8g/isProvidedBy          | https://joinup.ddev.local/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 |
      | https://joinup.ddev.local/assessment/report/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/2sa/cav#Assessment                                                      |
