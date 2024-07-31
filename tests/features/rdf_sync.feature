@api @group-i
Feature: RDF synchronization of contents.
  As an owner of the website
  in order for my news to be better visible on the web
  I need my RDF entities to be properly synchronized with the site contents.

  Scenario: RDF entities for EIF perspective are correctly created.
    Given the following solutions:
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
    Then only the following "http://data" graph triples should exist in the RDF backend:
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
    Then only the following "http://data" graph triples should exist in the RDF backend:
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
    Then only the following "http://data" graph triples should exist in the RDF backend:
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
    Then only the following "http://data" graph triples should exist in the RDF backend:
      | subject                      | predicate                                       | object                               |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Jocelyn Bass Bryce                   |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | jocelyn.bass.bryce@example.com       |
      | https://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://www.example.org               |
      | https://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |

  Scenario: RDF entities for Licences are correctly created.
    Given licence content:
      | uri                          | title              | description    | licence type  | deprecated  |
      | https://example.com/license1 | Some license       |                | Public domain | no          |
      | https://example.com/license2 | Some other license | A new licence. | Public domain | yes         |
    Then only the following "http://data" graph triples should exist in the RDF backend:
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
    Then only the following "http://data" graph triples should exist in the RDF backend:
      | subject                      | predicate                                       | object                                   |
      | https://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | https://example.com/license2 | http://purl.org/dc/terms/description            | <p>A new updated licence.</p>            |
      | https://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | https://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | https://example.com/license2 | http://joinup.eu/licence/deprecated             | 0                                        |
