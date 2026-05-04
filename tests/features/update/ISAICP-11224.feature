@api @group-clone
Feature: Trim leading and trailing whitespace from node titles.

  @update:joinup_core_deploy_201300
  Scenario: Node titles with leading or trailing whitespace are trimmed.
    When I go to "/collection/rolling-plan-ict-standardisation/accessibility-ict-products-and-services-0"
    Then I should see the text "ACCESSIBILITY OF ICT PRODUCTS AND SERVICES" in the "Page title" region
    And the response should not contain "  ACCESSIBILITY OF ICT PRODUCTS AND SERVICES"

    When I go to "/collection/rolling-plan-ict-standardisation/digitisation-european-industry-0"
    Then I should see the text "DIGITISATION OF EUROPEAN INDUSTRY" in the "Page title" region
    And the response should not contain "  DIGITISATION OF EUROPEAN INDUSTRY"
