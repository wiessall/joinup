@api @group-clone
Feature: Remove topic meta_entity and transfer data to taxonomy terms

  @update:topic_update_201502
  @update:topic_update_201503
  Scenario Outline: Topic tiles on the homepage link to the correct URL after migration.
    When I go to the homepage
    And I click "<title>"
    Then I should see the heading "<title>"
    And the relative url should be "<path>"

    Examples:
      | title                      | path                                                 |
      | Digital government         | /topic/digital-government/digital-government         |
      | Digital-ready policymaking | /topic/digital-government/digital-ready-policymaking |
      | Observatories              | /topic/digital-government/observatories              |
      | Digital innovation         | /topic/innovation/digital-innovation                 |
