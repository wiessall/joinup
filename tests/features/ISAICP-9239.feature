@api @group-clone
Feature: Test to check JsonLD on distribution.

  Scenario Outline: Check distribution cannot be serialized to JsonLD object.
    Given I visit "<url>"
    And the page should contain the html text '<script type="application/ld+json">'

    Examples:
      | url                                                                                                                                                           |
      | /collection/european-committee-standardization-cen/solution/metalex-open-xml-interchange-format-legal-and-legislative-resources/distribution/cwa-15710        |
      | /collection/european-interoperability-reference-architecture-eira/solution/cartography-tool/distribution/cartool-logo-v100-9                                  |
      | /collection/european-committee-standardization-cen/solution/mapping-between-dublin-core-and-iso-19115-geographic-information-metadata/distribution/cwa-14857  |
