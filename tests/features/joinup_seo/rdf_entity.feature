@api @group-g
Feature: SEO for RDF entities.
  As an owner of the website
  in order for my RDF entities to be better visible on the web
  I need proper meta information to be encapsulated in the html code.

  Scenario Outline: Basic JSON meta information for collections and solutions.
    Given <type> content:
      | title      |
      | SEO entity |

    When I visit the "SEO entity" <type>
    And the metatag graph of the item with "title" "SEO entity" should be present
    And 1 metatag graph of type "<graph type>" should exist in the page
    And the metatag graph of the item with "@type" "<graph type>" should have the following "http://purl.org/dc/terms/title" properties:
      | property  | value      |
      | @language | en         |
      | @value    | SEO entity |

    When I visit the <type> overview page
    Then the metatag graph of the item with "title" "SEO entity" should not be present

    Examples:
      | type       | graph type                        |
      | collection | http://www.w3.org/ns/dcat#Catalog |
      | solution   | http://www.w3.org/ns/dcat#Dataset |

  Scenario: Basic JSON meta information for releases and distributions.
    Given the following "solution" content:
      | title        |
      | SEO solution |
    And the following "release" content:
      | title       | is version of |
      | SEO release | SEO solution  |
    And the following "distribution" content:
      | title            | release     |
      | SEO distribution | SEO release |

    When I go to the "SEO release" release
    Then the metatag graph of the item with title "SEO release" should be present

    When I go to the "SEO distribution" distribution
    Then the metatag graph of the item with title "SEO distribution" should be present

  Scenario: Basic JSON meta information for licences.
    Given the following "licence" content:
      | title       | description            | licence type  |
      | SEO licence | Licence to perform SEO | Public domain |
    When I go to the "SEO licence" licence
    Then the following meta tags should available in the html:
      | identifier   | value                            |
      | og:url       | __base_url__/licence/seo-licence |
      | og:site_name | Interoperable Europe Portal      |
      | og:title     | SEO licence                      |
    And the metatag JSON should be attached in the page without context
    And 1 metatag graph of type "http://purl.org/dc/terms/LicenseDocument" should exist in the page
    And the metatag graph of the item with "@type" "http://purl.org/dc/terms/LicenseDocument" should have the following properties:
      | property                                   | value                                    |
      | @type                                      | http://purl.org/dc/terms/LicenseDocument |
      | http://www.w3.org/2000/01/rdf-schema#label | SEO licence                              |
      | http://joinup.eu/licence/deprecated        | false                                    |
      | description                                | Licence to perform SEO                   |
      | type                                       | http://purl.org/adms/licence/PD          |

  Scenario: Keywords are converted to literals in the normalization.
    Given the following "solution" content:
      | title        | keywords |
      | SEO solution | games    |
    When I go to the "SEO solution" solution
    Then the metatag graph of the item with title "SEO solution" should be present
    And the metatag graph of the item with "@type" "http://www.w3.org/ns/dcat#Dataset" should have the following properties:
      | property                          | value |
      | http://www.w3.org/ns/dcat#keyword | games |
