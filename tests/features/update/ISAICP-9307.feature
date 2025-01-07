@api @group-clone
Feature: Test download button for distributions.

  @update:joinup_core_update_200200 @javascript
  Scenario: Error message when I click on the button download.
    Given I visit "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-service-camssaas/distribution/camssaas-info-package-v800"
    And I click "Download"
    Then I should not see the message "Oops, something went wrong. Check your browser's developer console for more details."
