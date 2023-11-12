@api @group-clone @javascript
Feature: Accessibility

  Scenario Outline: Regression test for "Data protection and web accessibility
    compliance check" from February 3, 2023

    Given I visit "<page>"
    Then the page meets accessibility standards

    Examples:
      | page                                                                                                                            |
      | /                                                                                                                               |
      | /collection/egovernment/news/testa-trusted-solution-endorsed-member-states                                                      |
      | /collection/joinup/interoperability-solutions                                                                                   |
      | /interoperable-europe/interoperable-europe                                                                                      |
      | /collection/joinup/about                                                                                                        |
      | /search                                                                                                                         |
      | /collection/joinup/faq                                                                                                          |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23open-source-software                                                     |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23ict                                                                      |
      | /search?f[0]=type:news                                                                                                          |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23egovernment                                                              |
      | /collection/interoperability-test-bed-repository/solution/interoperability-test-bed/news/validator-plugin-guide                 |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic/category%23digital-government                                              |
      | /collection/open-data/news/discover-new-series-webinars-data-providers                                                          |
      | /search?f[0]=type:event                                                                                                         |
      | /collection/open-source-observatory-osor/event/open-source-summit-europe                                                        |
      | /search?f[0]=type:collection                                                                                                    |
      | /collection/nifo-national-interoperability-framework-observatory/news/publication-2022-digital-public-administration-factsheets |
      | /collection/eupl/news/jrc-and-fao-experts-licensing-assessment                                                                  |
      | /collection/open-data/news/register-finals-eu-datathon-2022                                                                     |
      | /collection/are3na/solution/re3gistry/news/re3gistry-2022-foss4g-firenze                                                        |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23content-and-knowledge-management                                         |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23standardisation                                                          |
      | /collection/open-source-observatory-osor/news/co2-tool-accounting-code                                                          |
