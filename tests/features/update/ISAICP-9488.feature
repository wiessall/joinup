@api @group-clone
Feature: Aliases are not updates.

  @update:joinup_core_deploy_200300
  Scenario Outline: Update collection alias.
    Given I am logged in as a user with the "moderator" role
    And I visit "<path>"
    And I fill in "Short ID" with "<Short ID>"
    And I press "Publish"
    When I should be on "<expected>"

    Examples:
      | path                                                                  | Short ID     | expected                     |
      | /collection/nifo-national-interoperability-framework-observatory/edit |              | /collection/iopeu-monitoring |
      | /interoperable-europe/edit                                            | inter-europe | /interoperable-europe        |

  @update:joinup_core_deploy_200300
  Scenario Outline: Update solution alias.
    Given I am logged in as a user with the "moderator" role
    And I visit "<path>"
    And I fill in "Short ID" with "<Short ID>"
    And I select "Architecture Principle" from "Solution type"
    And I press "Publish"
    When I should be on "<expected>"

    Examples:
      | path                                                                                    | Short ID   | expected                                                                           |
      | /collection/european-interoperability-reference-architecture-eira/solution/egovera/edit | egovera    | /collection/european-interoperability-reference-architecture-eira/solution/egovera |
      | /collection/statistics/solution/eurotrace/edit                                          | etsolution | /collection/statistics/solution/etsolution                                         |
