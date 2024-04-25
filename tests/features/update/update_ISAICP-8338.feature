@api @group-clone
Feature: Taxonomy terms are synchronizing to RDF backend.

  Scenario: Smoke test term synchronization

    # EIRA terms.
    Given I visit the page of the entity having "http://data.europa.eu/dr8/APIApplicationInterface" as RDF resource
    Then I should see the heading "API"
    And I should see the heading "EIRA properties"
    And the following "http://data" graph triples should exist in the RDF backend:
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept>                                    |
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/2004/02/skos/core#broaderTransitive> <http://data.europa.eu/dr8/view/TechnicalView-Application>                 |
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/2004/02/skos/core#broaderTransitive> <http://data.europa.eu/dr8/viewpoint/KeyInteroperabilityEnablersViewpoint> |
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/2004/02/skos/core#inScheme> <http://data.europa.eu/dr8>                                                         |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept>                         |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/2004/02/skos/core#related> <http://data.europa.eu/dr8/APIApplicationInterface>                       |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8>                                          |

    # EIF terms.
    Given I visit the page of the entity having "http://publications.europa.eu/resource/authority/file-type/GDB" as RDF resource
    Then I should see the heading "Esri File Geodatabase"
    And the following "http://data" graph triples should exist in the RDF backend:
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept> |
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://joinup.eu/file-type/extension> ".gdb"^^<http://www.w3.org/2001/XMLSchema#string>        |
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://www.w3.org/2004/02/skos/core#prefLabel> "Esri File Geodatabase"@en                      |

  Scenario Outline: Legacy RDF paths are redirected to the corresponding entities.
    Given I am an anonymous user
    When I visit "<path>"
    Then the response status code should be 200
    And the HTML title of the page should be "<title>"
    And the relative url should be "<url>"

    Examples:
      | path                                                                                  | title                                        | url                                                    |
      # Asset distribution
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fb67d9c7c_bc935_b475f_b9827_b6728d1161312 | DORIS 1.0                                    | /collection/doris/solution/doris/distribution/doris-10 |
      # Asset release
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fc99b57cd_bb1f9_b4cda_ba63a_b7ec83529d4bc | CCE 3.3.2                                    | /release/cce-332                                       |
      # Collection
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f506ed171_bd893_b458b_b8fdf_b037d089cbe24 | Science for Innovations in Public Governance | /collection/sipg                                       |
      # Solution
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f42a47775_bbc0c_b4f03_baaea_bcad61078e5fd | ECAS                                         | /collection/ecphp/solution/ecas                        |
      # Licence terms
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fb51b274c_b2698_b4cfc_bbfab_b2c7e217ab4cc | Python License                               | /licence/python-license                                |

    Scenario Outline: Legacy RDF paths accessible only by moderators
      Given I am logged in as a moderator
      When I visit "<path>"
      Then the response status code should be 200
      And the HTML title of the page should be "<title>"

      Examples:
        | path                                                                                                                       | title                                                                                 |
        # Contact information
        | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f46c8f61e_b4034_b4137_b9c01_bb0274cf3a50c                                      | Mathias Maurer                                                                        |
        # Owner
        | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fa1e67aea_b2988_b4801_ba9c8_ba1883fc702c8                                      | Dirección de Modernizacion de la Gestión y Gobierno Electrónico. Gobierno de Misiones |
