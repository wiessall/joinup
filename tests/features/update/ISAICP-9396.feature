@api @group-clone
Feature: Library import check.

  @update:joinup_core_deploy_200200
  Scenario: Check that all 3 EIRA vocabularies are imported in full.
    # @todo To be removed along with the step definition in the post update
    #   cleanup.
    Given all EIRA vocabularies are successfully imported.

  @update:joinup_core_deploy_200200
  Scenario: Ensure that the eGovERA terms are being redirected properly.
    Given I go to "data/dr8/egovera/NationalSchemaOnEDeliveryRequirement"
    Then I should see the heading "National Schema on eDelivery"
    And I should see the text "EIRA properties"
    # Text from the EIRA properties.
    And I should see the text "egovera:NationalSchemaOnEDeliveryRequirement"
