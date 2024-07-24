@api @group-clone
Feature: Test the releases update.

  @update:joinup_rdf_deploy_111101
  Scenario: Releases with their fields have migrated correctly.
    And I visit "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-assessment-prov-family-eif-scenario/release/100"
    Then I should see the text "CAMSS Assessment of PROV Family of documents 1.0.0"
    # Published at field.
    And I should see the text "Published on: 19/07/2023"
    # Modification field is not the date of the migration.
    And I should see the text "Last update: 07/08/2023"
    # Release notes field.
    And I should see the text "About the Common Assessment Method for Standards and Specifications"

    # Assert that the distributions appear as tiles.
    And I should see the "CAMSS Assessment Summary of  PROV 1.0.0 EIF Scenario v1.0.0" tile

    # Draft releases are migrated too.
    Given I visit "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-assessment-dns-eif-scenario/release/300"
    Then I should see the heading "Sign in to continue"
    When I am logged in as a moderator
    And I visit "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-assessment-dns-eif-scenario/release/300"
    Then I should see the text "CAMSS Assessment of DNS - EIF Scenario 3.0.0"
    And I should see the link "Switch to the latest release"

  @update:joinup_rdf_deploy_111102
  Scenario: Forward revisions of releases are created correctly.
    When I am logged in as a moderator
    And I visit "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-assessment-prov-family-eif-scenario/release/100"
    And I click "Revisions" in the "Entity actions" region
    Then I should see the text "Current revision"
    And I should see the link "Set as current revision"
