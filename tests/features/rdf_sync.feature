@api @group-i
Feature: RDF synchronization of contents.
  As an owner of the website
  in order for my news to be better visible on the web
  I need my RDF entities to be properly synchronized with the site contents.

  Scenario: RDF entities for licences are correctly created
    Given licence content:
      | uri                          | title              | description    | licence type  | deprecated  |
      | https://example.com/license1 | Some license       |                | Public domain | no          |
      | https://example.com/license2 | Some other license | A new licence. | Public domain | yes         |
    Then the following "http://data" graph triples should exist in the RDF backend:
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
    When licence content "Some other license" is updated:
      | description | A new updated licence. |
      | deprecated  | 0                      |
    Then the following "http://data" graph triples should exist in the RDF backend:
      | subject                      | predicate                                       | object                                   |
      | https://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | https://example.com/license2 | http://purl.org/dc/terms/description            | <p>A new updated licence.</p>            |
      | https://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | https://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | https://example.com/license2 | http://joinup.eu/licence/deprecated             | 0                                        |
