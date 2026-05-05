@api
Feature: RDF Sync integration for assessments.

  Scenario: Test that an entire chain of entities is exported properly.
    Given solution content:
      | title           | uri                                        |
      | Citizen's Forum | http://example.com/solution/citizens-forum |
    And "participation" assessment:
      | uri                                                            | name          | stakeholder_country | participant_type         |
      | http://data.europa.eu/s2k/f5379633-58af-49bd-ab71-22d8ab77d59a | Stakeholder 1 | Belgium             | local_public_sector_body |
    And "public_service" assessment:
      | uri                                                            | name             | identifier | thematic_area           |
      | http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | Public Service 1 | PS-001     | general_public_services |
    And "public_organisation" assessment:
      | uri                                                            | name                  | organisation_type          | country |
      | http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018 | Public Organisation 1 | european_union_institution | Belgium |
    And "expression" assessment:
      | uri                                                            | initiative_name | description   | documented_in       |
      | http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47 | Expression 1    | Description 1 | https://example.com |
    And the following "binding_requirement" paragraph:
      | uri               | http://data.europa.eu/s2k/a478fe88-c240-4769-8952-7d129f4ef449 |
      | id                | 999                                                            |
      | description       | BR Description 1                                               |
      | expression        | Expression 1                                                   |
      | affects           | Public Service 1                                               |
      | has_participation | Stakeholder 1                                                  |
      | asset             | Citizen's Forum                                                |
    And the following assessment statements:
      | uri                                                            | id  | statement_type | interpretation | judgement | landing_page |
      | http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361 | 999 | legal          | positive       | Compliant | text.pdf     |
    And "report" assessment:
      | uri                                                            | name     | binding_requirement | results_in | provider              | documents |
      | http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | Report 1 | 999                 | 999        | Public Organisation 1 | text.pdf  |
    And I am logged in as a moderator

    When I visit the export page of the entity having "http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae" as RDF resource in "turtle" format
    Then the response should contain the following lines of text:
      # Prefixes used in the export.
      | @prefix cav: <http://data.europa.eu/2sa/cav#> .                                                                                                                                                                  |
      | @prefix cv: <http://data.europa.eu/m8g/> .                                                                                                                                                                       |
      | @prefix skos: <http://www.w3.org/2004/02/skos/core#> .                                                                                                                                                           |
      | @prefix dc: <http://purl.org/dc/terms/> .                                                                                                                                                                        |
      | @prefix org: <http://www.w3.org/ns/org#> .                                                                                                                                                                       |
      | @prefix iopa: <http://data.europa.eu/2qy/iopa#> .                                                                                                                                                                |
      | @prefix dcat: <http://www.w3.org/ns/dcat#> .                                                                                                                                                                     |
      | @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .                                                                                                                                                               |
      # Assessment Report is exported.
      | <http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae>                                                                                                                                                 |
      | a cav:Assessment ;                                                                                                                                                                                               |
      | cav:assesses <http://data.europa.eu/s2k/a478fe88-c240-4769-8952-7d129f4ef449> ;                                                                                                                                  |
      | cav:resultsIn <http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361> ;                                                                                                                                 |
      | cv:isProvidedBy <http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018> .                                                                                                                               |
      | <http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018>                                                                                                                                                 |
      # Public Organisations (providers) are exported.
      | a cv:PublicOrganisation ;                                                                                                                                                                                        |
      | skos:prefLabel "Public Organisation 1"@en ;                                                                                                                                                                      |
      | dc:spatial <http://example.com/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66> ;                                                                                                                     |
      | org:classification <http://example.com/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25> .                                                                                                |
      # Binding Requirements are exported.
      | <http://data.europa.eu/s2k/a478fe88-c240-4769-8952-7d129f4ef449>                                                                                                                                                 |
      | a iopa:BindingRequirement ;                                                                                                                                                                                      |
      | iopa:affects <http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af> ;                                                                                                                                  |
      | dc:relation <http://example.com/solution/citizens-forum> ;                                                                                                                                                       |
      | cav:valueDescription "BR Description 1"@en ;                                                                                                                                                                     |
      | dc:isPartOf <http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47> ;                                                                                                                                   |
      | cv:hasParticipation <http://data.europa.eu/s2k/f5379633-58af-49bd-ab71-22d8ab77d59a> .                                                                                                                           |
      # Initiatives (Expressions) are exported.
      | <http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47>                                                                                                                                                 |
      | a <http://purl.org/vocab/frbr/core#Expression> ;                                                                                                                                                                 |
      | dc:description "Description 1" ;                                                                                                                                                                                 |
      | cav:documentedIn <https://example.com> ;                                                                                                                                                                         |
      | dc:title "Expression 1"@en .                                                                                                                                                                                     |
      # Affected services are exported.
      | <http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af>                                                                                                                                                 |
      | a <http://purl.org/vocab/cpsv#PublicService> ;                                                                                                                                                                   |
      | dc:title "Public Service 1"@en ;                                                                                                                                                                                 |
      | cv:thematicArea <http://example.com/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b> .                                                                                             |
      # Participation is exported.
      | <http://data.europa.eu/s2k/f5379633-58af-49bd-ab71-22d8ab77d59a>                                                                                                                                                 |
      | a cv:Participation ;                                                                                                                                                                                             |
      | dc:type <http://example.com/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336> .                                                                                                        |
      | <http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361>                                                                                                                                                 |
      | a cav:Statement ;                                                                                                                                                                                                |
      | cav:judgement "Compliant"@en ;                                                                                                                                                                                      |
      | cav:type <http://example.com/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47> ;                                                                                                         |
      | foaf:page <__base_url__/sites/default/files/text.pdf> .                                                                                                                                                          |
      | <__base_url__/sites/default/files/text.pdf> a foaf:Document                                                                                                                                                      |
      # Asset is exported.
      | <http://example.com/solution/citizens-forum>                                                                                                                                                                     |
      | a dcat:Dataset ;                                                                                                                                                                                                 |
      | dc:title "Citizen's Forum"@en ;                                                                                                                                                                                  |
      # Terms are exported alongside the main entities.
      | <http://example.com/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66>                                                                                                                                  |
      | skos:inScheme <http://publications.europa.eu/resource/authority/country>, <http://publications.europa.eu/resource/authority/country/0001>, <http://publications.europa.eu/resource/authority/country/0005> ;     |
      | skos:prefLabel "Belgium"@en ;                                                                                                                                                                                    |
      | skos:definition ""@en ;                                                                                                                                                                                          |
      | a dc:Location, skos:Concept ;                                                                                                                                                                                    |
      | skos:topConceptOf <http://publications.europa.eu/resource/authority/country>, <http://publications.europa.eu/resource/authority/country/0001>, <http://publications.europa.eu/resource/authority/country/0005> . |
      | <http://example.com/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25>                                                                                                                     |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "European Union institution"@en ;                                                                                                                                                                 |
      | skos:altLabel "european_union_institution"^^xsd:string ;                                                                                                                                                         |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <http://example.com/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b>                                                                                                               |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "General public services"@en ;                                                                                                                                                                    |
      | skos:altLabel "general_public_services"^^xsd:string ;                                                                                                                                                            |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <http://example.com/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336>                                                                                                                  |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "Local public sector body"@en ;                                                                                                                                                                   |
      | skos:altLabel "local_public_sector_body"^^xsd:string ;                                                                                                                                                           |
      | a skos:Concept .                                                                                                                                                                                                 |
      | <http://example.com/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47>                                                                                                                    |
      | skos:inScheme iopa:Code ;                                                                                                                                                                                        |
      | skos:prefLabel "Legal"@en ;                                                                                                                                                                                      |
      | skos:altLabel "legal"^^xsd:string ;                                                                                                                                                                              |
      | a skos:Concept .                                                                                                                                                                                                 |

    # Verify the data of the exported entities is correct.
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                                                                      |
      | http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361 | http://data.europa.eu/2sa/cav#judgement         | Compliant                                                                                   |
      | http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361 | http://data.europa.eu/2sa/cav#type              | http://example.com/taxonomy-term/assess-statement-type/219d8117-fd8e-4455-830d-9a0c6e81ab47 |
      | http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/2sa/cav#Statement                                                     |
      | http://data.europa.eu/s2k/e35d0e39-31c5-4090-b16d-c35d79695361 | http://xmlns.com/foaf/0.1/document              | __base_url__/sites/default/files/text.pdf                                                   |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                   | predicate                                       | object                             |
      | __base_url__/sites/default/files/text.pdf | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Document |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                     |
      | http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47 | http://data.europa.eu/2sa/cav#documentedIn      | https://example.com                        |
      | http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47 | http://purl.org/dc/terms/description            | Description 1                              |
      | http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47 | http://purl.org/dc/terms/title                  | Expression 1                               |
      | http://data.europa.eu/s2k/048caacf-52d6-4215-a867-a485ff4baf47 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/vocab/frbr/core#Expression |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                                                                        |
      | http://data.europa.eu/s2k/f5379633-58af-49bd-ab71-22d8ab77d59a | http://purl.org/dc/terms/type                   | http://example.com/taxonomy-term/assess-participant-type/b8c59bef-e579-440a-91f0-8c7983524336 |
      | http://data.europa.eu/s2k/f5379633-58af-49bd-ab71-22d8ab77d59a | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/m8g/Participation                                                       |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                                                                     |
      | http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018 | http://purl.org/dc/terms/spatial                | http://example.com/taxonomy-term/spatial/dfa6aecc-1a7d-4bee-83b7-363b0db1ba66              |
      | http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/m8g/PublicOrganisation                                               |
      | http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/2004/02/skos/core#prefLabel   | Public Organisation 1                                                                      |
      | http://data.europa.eu/s2k/542770eb-a1e8-44fe-be6d-d48c33837018 | http://www.w3.org/ns/org#classification         | http://example.com/taxonomy-term/assess-provider-type/5870cf04-2d28-472d-9486-39b1e4670b25 |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                                                                           |
      | http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://data.europa.eu/m8g/thematicArea          | http://example.com/taxonomy-term/assess-public-service-area/34229fe8-14ac-40f8-9434-765e441eae3b |
      | http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://purl.org/dc/terms/title                  | Public Service 1                                                                                 |
      | http://data.europa.eu/s2k/d4a2ecb3-c0c8-4a13-9dd0-8dddb23c40af | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/vocab/cpsv#PublicService                                                         |

    And the following "http://data" graph triples exist in the RDF backend:
      | subject                                                        | predicate                                       | object                                                                                 |
      | http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/2sa/cav#assesses          | http://example.com/paragraph/binding-requirement/a478fe88-c240-4769-8952-7d129f4ef449  |
      | http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/2sa/cav#resultsIn         | http://example.com/assessment-statement/legal/e35d0e39-31c5-4090-b16d-c35d79695361     |
      | http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://data.europa.eu/m8g/isProvidedBy          | http://example.com/assessment/public-organisation/542770eb-a1e8-44fe-be6d-d48c33837018 |
      | http://data.europa.eu/s2k/8a7b9a07-5408-4cec-9e3b-dc9df4e783ae | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/2sa/cav#Assessment                                               |
