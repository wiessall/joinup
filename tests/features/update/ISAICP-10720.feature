@api @group-clone
Feature: Remove topic meta_entity and transfer data to taxonomy terms

  @update:topic_update_201501
  @update:topic_update_201502
  Scenario Outline: Topic tiles on the homepage display the correct title and abstract after migration.
    When I go to the homepage
    Then I should see the text "<title>" in the "Homepage topics" region
    And I should see the text "<abstract>" in the "Homepage topics" region

    When I click "<title>" in the "Homepage topics" region
    Then I should see the heading "<title>"
    And I should see the text "<description>"

    Examples:
      | title                      | abstract                                                                                                                              | description                                                                                            |
      | Digital government         | Effective digital public services can provide a wide variety of benefits.                                                             | Effective digital public services can provide a wide variety of benefits. These include                |
      | Digital-ready policymaking | Digital-ready policymaking considers digital aspects from start to finish to create policies that are future-proof and interoperable. | Digital-ready policymaking refers to the process of formulating digital-ready policies and legislation |

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
