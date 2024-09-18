@api
Feature: RDF synchronization of contents.
  As an owner of the website
  in order for my news to be better visible on the web
  I need my RDF entities to be properly synchronized with the site contents.

  Scenario: RDF entities for EIF perspective are correctly created.
    Given solution content:
      | title        | description                                      | state     | uri                             |
      | EIF solution | Some description not to be shown in the EIF page | published | https://example.com/collection1 |
    Given the following EIF perspective data for the "EIF solution" solution:
      | Description | This should be in the description field.                                                       |
      | Principle   | Underlying Principle 1: Subsidiarity and proportionality, Underlying Principle 3: Transparency |
      | Layer       | Interoperability Layer 3: Legal interoperability                                               |
      | uri         | https://example.com/eif-perspective1                                                           |
    And the following EIF perspective data for the "EIF solution" solution:
      | Description | Description field of the second item.                 |
      | Principle   | Underlying Principle 3: Transparency                  |
      | Layer       | Interoperability Layer 1: Interoperability governance |
      | Model       | Basic Component 4: Open data                          |
      | uri         | https://example.com/eif-perspective2                  |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                              | predicate                                       | object                                        |
      | https://example.com/eif-perspective1 | http://purl.org/dc/terms/description            | This should be in the description field.      |
      | https://example.com/eif-perspective1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF |
      | https://example.com/eif-perspective1 | http://www.w3.org/ns/adms#aboutAsset            | https://example.com/collection1               |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL3                     |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP1                     |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                     |
      | https://example.com/eif-perspective2 | http://purl.org/dc/terms/description            | Description field of the second item.         |
      | https://example.com/eif-perspective2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF |
      | https://example.com/eif-perspective2 | http://www.w3.org/ns/adms#aboutAsset            | https://example.com/collection1               |
      | https://example.com/eif-perspective2 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL1                     |
      | https://example.com/eif-perspective2 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                     |
      | https://example.com/eif-perspective2 | http://data.europa.eu/eif/Model                 | http://data.europa.eu/BC4                     |
    When I update the entity having "https://example.com/eif-perspective1" as RDF resource:
      | eif_description | This should be in the new description field. |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                              | predicate                                       | object                                              |
      | https://example.com/eif-perspective1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF       |
      | https://example.com/eif-perspective1 | http://purl.org/dc/terms/description            | <p>This should be in the new description field.</p> |
      | https://example.com/eif-perspective1 | http://www.w3.org/ns/adms#aboutAsset            | https://example.com/collection1                     |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL3                           |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP1                           |
      | https://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                           |

  Scenario: RDF entities for Contact information are correctly created.
    Given contact_information content:
      | uri                          | name           | email                    | website url            |
      | https://example.com/contact1 | Jocelyn Bass   | jocelyn.bass@example.com | http://www.example.org |
      | https://example.com/contact2 | Geoffrey Bryce | geoffrey.bryce@exam.com  |                        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                               |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Jocelyn Bass                         |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | jocelyn.bass@example.com             |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://www.example.org               |
      | https://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |
      | https://example.com/contact2 | http://www.w3.org/2006/vcard/ns#fn              | Geoffrey Bryce                       |
      | https://example.com/contact2 | http://www.w3.org/2006/vcard/ns#hasEmail        | geoffrey.bryce@exam.com              |
      | https://example.com/contact2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |
    When I update the entity having "https://example.com/contact1" as RDF resource:
      | title | Jocelyn Bass Bryce             |
      | email | jocelyn.bass.bryce@example.com |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                               |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Jocelyn Bass Bryce                   |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | jocelyn.bass.bryce@example.com       |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://www.example.org               |
      | https://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |

  Scenario: RDF entities for Distribution are correctly created.
    Given solution content:
      | title         | state     |
      | Test solution | published |
    And release content:
      | title        | is version of | state     |
      | Test release | Test solution | published |
    And distribution content:
      | uri                               | title          | description                  | release      | access url                      | created              | changed              | state     |
      | https://example.com/distribution1 | Some Distro    | Übermensch foot size         | Test release | http://example.com/some-distro1 | 2024-07-31T10:19:33Z | 2024-07-31T10:19:33Z | published |
      | https://example.com/distribution2 | Another distro | Description for other distro | Test release | http://example.com/some-distro2 | 2024-08-01T10:19:33Z | 2024-08-01T10:19:33Z | published |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                           | predicate                                       | object                                 |
      | https://example.com/distribution1 | http://purl.org/dc/terms/title                  | Some Distro                            |
      | https://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | https://example.com/distribution1 | http://purl.org/dc/terms/description            | <p>Übermensch foot size</p>            |
      | https://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro1        |
      | https://example.com/distribution1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                   |
      | https://example.com/distribution1 | http://purl.org/dc/terms/modified               | 2024-07-31T10:19:33Z                   |
      | https://example.com/distribution2 | http://purl.org/dc/terms/title                  | Another distro                         |
      | https://example.com/distribution2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | https://example.com/distribution2 | http://purl.org/dc/terms/description            | <p>Description for other distro</p>    |
      | https://example.com/distribution2 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro2        |
      | https://example.com/distribution2 | http://purl.org/dc/terms/issued                 | 2024-08-01T10:19:33Z                   |
      | https://example.com/distribution2 | http://purl.org/dc/terms/modified               | 2024-08-01T10:19:33Z                   |
    When I update the entity having "https://example.com/distribution1" as RDF resource:
      | title       | Some Distro new title       |
      | description | Some Distro new description |
      | changed     | 2024-07-31T15:20:00Z        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                           | predicate                                       | object                                 |
      | https://example.com/distribution1 | http://purl.org/dc/terms/title                  | Some Distro new title                  |
      | https://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | https://example.com/distribution1 | http://purl.org/dc/terms/description            | <p>Some Distro new description</p>     |
      | https://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro1        |
      | https://example.com/distribution1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                   |
      | https://example.com/distribution1 | http://purl.org/dc/terms/modified               | 2024-07-31T15:20:00Z                   |

  Scenario: RDF entities for Licences are correctly created.
    Given licence content:
      | uri                          | title              | description    | licence type  | deprecated |
      | https://example.com/license1 | Some license       |                | Public domain | no         |
      | https://example.com/license2 | Some other license | A new licence. | Public domain | yes        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                   |
      | https://example.com/license1 | http://www.w3.org/2000/01/rdf-schema#label      | Some license                             |
      | https://example.com/license1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | https://example.com/license1 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | https://example.com/license1 | http://joinup.eu/licence/deprecated             | 0                                        |
      | https://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | https://example.com/license2 | http://purl.org/dc/terms/description            | <p>A new licence.</p>                    |
      | https://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | https://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | https://example.com/license2 | http://joinup.eu/licence/deprecated             | 1                                        |
    When I update the entity having "https://example.com/license2" as RDF resource:
      | description | A new updated licence. |
      | deprecated  | 0                      |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                   |
      | https://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | https://example.com/license2 | http://purl.org/dc/terms/description            | <p>A new updated licence.</p>            |
      | https://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | https://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | https://example.com/license2 | http://joinup.eu/licence/deprecated             | 0                                        |

  Scenario: RDF entities for Owners and Owner types are correctly created.
    Given owner_type terms:
      | uri                          | name       |
      | http://joinup.eu/owner-type1 | University |
    And owner content:
      | uri                        | name          | state     | owner type |
      | https://example.com/owner1 | Teddy Bass    | published | University |
      | https://example.com/owner2 | Johnny Guitar | published | University |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                      |
      | https://example.com/owner1   | http://xmlns.com/foaf/0.1/name                  | Teddy Bass                                  |
      | https://example.com/owner1   | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent             |
      | https://example.com/owner1   | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1                |
      | https://example.com/owner2   | http://xmlns.com/foaf/0.1/name                  | Johnny Guitar                               |
      | https://example.com/owner2   | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent             |
      | https://example.com/owner2   | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1                |
      | http://joinup.eu/owner-type1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2004/02/skos/core#Concept |
      | http://joinup.eu/owner-type1 | http://www.w3.org/2004/02/skos/core#inScheme    | http://purl.org/adms/publishertype/1.0      |
      | http://joinup.eu/owner-type1 | http://www.w3.org/2004/02/skos/core#prefLabel   | University                                  |
    When I update the entity having "https://example.com/owner1" as RDF resource:
      | title | Stephen Bass |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                    | predicate                                       | object                          |
      | https://example.com/owner1 | http://xmlns.com/foaf/0.1/name                  | Stephen Bass                    |
      | https://example.com/owner1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent |
      | https://example.com/owner1 | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1    |

  Scenario: RDF entities for Releases are correctly created.
    Given solution content:
      | title       | state     | uri                           |
      | Early Omega | published | https://example.com/solution1 |
    And release content:
      | uri                          | title    | documentation | release number | release notes | published_at         | changed              | is version of | state     | status    | keywords   |
      | https://example.com/release1 | Omega3   | text.pdf      | 3.0.0          | New 3.0       | 2024-07-31T10:19:33Z | 2024-07-31T10:19:33Z | Early Omega   | published | Completed | food       |
      | https://example.com/release2 | Omega3.1 | text.pdf      | 3.1.0          | New 3.1       | 2024-08-01T10:19:33Z | 2024-08-01T10:19:33Z | Early Omega   | published | Completed | food,games |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                |
      | https://example.com/release1 | http://purl.org/dc/terms/title                  | Omega3                                |
      | https://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | https://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                 |
      | https://example.com/release1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                  |
      | https://example.com/release1 | http://purl.org/dc/terms/modified               | 2024-07-31T10:19:33Z                  |
      | https://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | https://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | https://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | https://example.com/solution1         |
      | https://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                               |
      | https://example.com/release2 | http://purl.org/dc/terms/title                  | Omega3.1                              |
      | https://example.com/release2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | https://example.com/release2 | http://www.w3.org/2002/07/owl#versionInfo       | 3.1.0                                 |
      | https://example.com/release2 | http://purl.org/dc/terms/issued                 | 2024-08-01T10:19:33Z                  |
      | https://example.com/release2 | http://purl.org/dc/terms/modified               | 2024-08-01T10:19:33Z                  |
      | https://example.com/release2 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | https://example.com/release2 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | https://example.com/release2 | http://www.w3.org/ns/dcat#keyword               | games                                 |
      | https://example.com/release2 | http://purl.org/dc/terms/isVersionOf            | https://example.com/solution1         |
      | https://example.com/release2 | http://www.w3.org/ns/adms#versionNotes          | New 3.1                               |
    When I update the entity having "https://example.com/release1" as RDF resource:
      | title          | Omega the third      |
      | field_keywords | food,drinks          |
      | changed        | 2024-07-31T15:20:00Z |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                |
      | https://example.com/release1 | http://purl.org/dc/terms/title                  | Omega the third                       |
      | https://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | https://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                 |
      | https://example.com/release1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                  |
      | https://example.com/release1 | http://purl.org/dc/terms/modified               | 2024-07-31T15:20:00Z                  |
      | https://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | https://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | https://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | drinks                                |
      | https://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | https://example.com/solution1         |
      | https://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                               |
