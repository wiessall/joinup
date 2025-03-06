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

    # Ensure the upstream redirection works as well.
    Given I go to "/taxonomy/term/http_e_f_fdata_ceuropa_ceu_fdr8_fegovera/InvoiceBusinessObject"
    Then I should see the heading "Invoice"
    And I should see the text "EIRA properties"
    # Text from the EIRA properties.
    And I should see the text "egovera:InvoiceBusinessObject"

  @update:joinup_core_deploy_200301
  Scenario: Sample check that options are available in the solution type field.
    Given I am logged in as a moderator
    And I go to the edit form of the "Joinup source code" solution
    And the option "-Interoperable European Solution" should be selected
    # Assert that some options exist.
    And I select "-Logging Service" from "Solution type"
    And I select "-Key Interoperability Enabler" from "Solution type"
