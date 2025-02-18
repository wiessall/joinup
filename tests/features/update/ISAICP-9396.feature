@api @group-clone
Feature: Library import check.

  @update:joinup_core_deploy_200301
  Scenario: Check that all 3 EIRA vocabularies are imported in full.
    Given all EIRA vocabularies are successfully imported.

    # Ensure redirection to the eGovERA terms.
    Given I go to "data/dr8/egovera/NationalSchemaOnEDeliveryRequirement"
    Then I should see the heading "National Schema on eDelivery"
    And I should see the text "EIRA properties"
    # Text from the EIRA properties.
    And I should see the text "egovera:NationalSchemaOnEDeliveryRequirement"

    # Ensure redirection to the eGovERA terms.
    Given I go to "data/dr8/InformationBusinessObject"
    Then I should see the heading "Information"
    And I should see the text "EIRA properties"
    # Text from the EIRA properties.
    And I should see the text "eira:InformationBusinessObject"
