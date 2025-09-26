@api @group-clone @javascript
Feature: Accessibility

  Scenario Outline: Regression test for "Data protection and web accessibility
    compliance check" from February 3, 2023

    Given I visit "<page>"
    Then the page meets accessibility standards

    Examples:
      | page                                                                                                                            |
      # This list of case pages needs revision.
      | /                                                                                                                               |
      | /collection/are3na/solution/re3gistry/news/re3gistry-2022-foss4g-firenze                                                        |
      | /collection/egovernment/news/testa-trusted-solution-endorsed-member-states                                                      |
      | /collection/eupl/news/jrc-and-fao-experts-licensing-assessment                                                                  |
      | /collection/govtechconnect/news/register-govtech-connect-govtech4all-webinar                                                    |
      | /collection/interoperability-test-bed-repository/solution/interoperability-test-bed/news/validator-plugin-guide                 |
      | /collection/joinup                                                                                                              |
      | /collection/portal/about                                                                                                        |
      | /collection/portal/faq                                                                                                          |
      | /collection/portal/interoperability-solutions                                                                                   |
      | /collection/nifo-national-interoperability-framework-observatory/news/publication-2022-digital-public-administration-factsheets |
      | /collection/open-data/news/discover-new-series-webinars-data-providers                                                          |
      | /collection/open-data/news/register-finals-eu-datathon-2022                                                                     |
      | /collection/open-source-observatory-osor/news/co2-tool-accounting-code                                                          |
      | /collection/shared-solutions-public-sector-catalonia-aoc-consortium/solution/tools-e-procurement-local-authorities-catalonia    |
      | /collection/digital-skills-public-sector/solution/iop-bdti                                                                      |
      | /interoperable-europe/event/register-biggest-2023-open-source-conference-brussels                                               |
      | /interoperable-europe/news/govtech-incubator-launched-2023-digital-government-summit                                            |
      | /search                                                                                                                         |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23content-and-knowledge-management                                         |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23egovernment                                                              |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23ict                                                                      |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23open-source-software                                                     |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic%23standardisation                                                          |
      | /search?f[0]=topic:http%3A//joinup.eu/ontology/topic/category%23digital-government                                              |
      | /search?f[0]=type:collection                                                                                                    |
      | /search?f[0]=type:event                                                                                                         |
      | /search?f[0]=type:news                                                                                                          |

      # New pages added after aligning the AXE tags with accessibility-cloud app.
      | /collection/european-interoperability-reference-architecture-eira/solution/egovera-business-agnostic-0/release/610              |
      | /eu-oss-catalogue                                                                                                               |
      | /eu-oss-catalogue/solutions/bundid-keycloak-adapter-mhkbd-nrw                                                                   |
      | /collection/invest-interoperability-vision-europe-strategy-timeplan/event/invest-project-initial-knowledge-event                |
      | /topic/ict/open-source-software                                                                                                 |
      | /interoperable-europe                                                                                                           |
      | /licence/bsd-2-clause-simplified-license                                                                                        |
      | /collection/interoperable-europe-academy/courses-curriculum                                                                     |

  Scenario Outline: Empty carousel headings
    Given I visit "<page>"
    Then the page meets "Empty heading elements" accessibility standards

    Examples:
      | page                                                                 |
      | /collection/iopeu-monitoring/policy-context                          |
      | /collection/interoperable-europe-academy/seasonal-school             |
      | /interoperable-europe/news/new-record-number-participants-semic-2024 |
      | /collection/interoperable-europe-academy/event/seasonal-school-2025  |
