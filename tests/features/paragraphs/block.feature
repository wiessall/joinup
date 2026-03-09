@api @group-h
Feature: Block paragraph tests.

  Background:
    Given collection content:
      | title            |
      | Block collection |

  @javascript
  Scenario: Recommended solutions
    Given solution content:
      | title        | collection       |
      | Early Omega  | Block collection |
      | Snake Timely | Block collection |
    And custom_page content:
      | title                 | collection       | abstract             |
      | Recommended solutions | Block collection | Recommended abstract |
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Recommended solutions" title:
      | paragraph_block | views_block:recommended_solutions-recommended |

    Given I am logged in as a user with the "interoperability_validator" role
    When I go to the homepage of the "Early Omega" solution
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed

    When I am an anonymous user
    And I visit the "Recommended solutions" "custom page"
    Then I should see the "Early Omega" tile
    But I should not see the "Snake Timely" tile

  Scenario: Oss catalogue sources
    Given custom_page content:
      | title                 | collection       |
      | Oss catalogue sources | Block collection |
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Oss catalogue sources" title:
      | paragraph_block | joinup_oss_catalogue_sources |

    When I go to the "Oss catalogue sources" custom_page
    Then the "OSS catalogue list" table should be:
      | Catalogue          | Type     | Country     | Description                                                                                                                                                                                                                                                                       | Weblinks                                  |
      | Avoinkoodi.fi      | National | Finland     | Avoinkoodi.fi catalogue lists together software used in Finnish government, education and the local government sector, whose source code has been published openly.                                                                                                               | http://avoinkoodi.fi                      |
      | Awesome CodeGouvFr | National | France      | CodeGouvFr, the French National Catalogue with mission to use and contribute to free software and digital commons provides. Awesome Open Source projects actively maintained and funded by Public Administrations and Public Sector organizations.                                | https://code.gouv.fr/sources/#/awesome    |
      | City of Ghent      | Local    | Belgium     | The City of Ghent's open-source software catalogue showcases digital solutions developed and used by the local administration to promote transparency, innovation, and interoperability.                                                                                          | Digitaal gedreven door Gent \| District09 |
      | Code Europa EU     | European |             | Code development platform for Open Source projects shared by the institutions of the European Union, for which EU institutions, bodies, offices and agencies hold the intellectual property rights.                                                                               | https://code.europa.eu                    |
      | Developer Overheid | National | Netherlands | Developer Overheid is the Dutch National Catalogue, one central location for OSS Solutions developed by and for Dutch government organizations.                                                                                                                                   | https://developer.overheid.nl             |
      | Developers Italia  | National | Italy       | Developers Italia is a community of developers who design and code Italian digital public services, and provide the Italian National Catalogue for the acquisition and reuse of software by and for the Public Administration, as envisaged and required by national legislation. | https://developers.italia.it/en/software  |
      | Offentligkod.se    | National | Sweden      | Offentlig Kod Swedish National Catalogue advocates open-source solutions for public sector IT, sharing reusable code and fostering collaboration & transparency in government projects.                                                                                           | https://offentligkod.se                   |
      | Open CoDE          | National | Germany     | Open CoDE is the German National Catalogue, an official, certified and secure central platform for providing direct access to validated open-source-solutions successfully used in other Public Administrations.                                                                  | https://opencode.de/en/software           |
      | Open Code Belgium  | National | Belgium     | Catalogue of open source projects developed by/for federal, regional and local public administrations in Belgium.                                                                                                                                                                 | https://www.imio.be                       |
      | Other              | European |             | Open Source solutions that are not part of any national, regional or local catalogues but are present in EU OS Solutions Catalogue.                                                                                                                                               |                                           |
