@api @group-c @javascript
Feature: Provided by widget functionality
  Test the "Provided by" widget behavior including organisation creation,
  selection, and validation

  Scenario: All possible tests for 'Provided by' plugin in assessment report
    Given public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |
    And I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"

    When I fill in "Short title" with "Test Provided by in Assessment Report 2025"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "GR1"

    # Verify organization is NOT created when form has validation errors.
    # Regression test - organizations should only be created on successful save.
    Then I press "Save as draft"
    # Should see validation error
    Then I should see the error message "1 error has been found: Binding requirements"
    # Organization should NOT have been created
    Then the public_organisation assessment "GR1" should not exist

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    # Save Assessment Report.
    Then I press "Save as draft"
    Then I should see the success message containing "New assessment Test Provided by in Assessment Report 2025 has been created."
    And I should see the heading "Test Provided by in Assessment Report 2025"

    # Edit to test all changes in widget.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "New organisation name"
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"

    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
    And I select "Test Organization" from "Select organisation"
    Then I press "Update"

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region

    # Change to local and change back to original state.
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "New organisation name"
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
    And no option from select "Select organisation" is selected
    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"

    # Verify organization is only created when the main assessment form is saved.
    # Regression test for ticket ISAICP-10555
    Then I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Organisation Should Not Be Created"
    Then I press "…or select existing"
    And I wait for AJAX to finish

    # Check if organisation is created.
    Then the public_organisation assessment "Organisation Should Not Be Created" should not exist
    And the following field should be present "Select organisation"
    And the following field should not be present "New organisation name"
    And I select "GR1" from "Select organisation"
    Then I press "Update"
    Then I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    Then the public_organisation assessment "Organisation Should Not Be Created" should not exist

    # Test case-insensitive matching for local organizations.
    # Regression test for case-insensitive name matching.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "gr1"
    Then I press "Update"
    Then I should see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test that NEW organizations are created without warnings.
    # Regression test for false positive warning messages on new organization creation.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Brand New Belgian Organisation 2025"
    Then I press "Update"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test that a second NEW organization with different name is also created without warnings.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Another New Belgian Organisation 2025"
    Then I press "Update"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test that editing and saving without changing local organization shows no warning.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Short title" with "Test Provided by in Assessment Report 2025 - Updated"
    Then I press "Update"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Updated has been updated."

   # Test that editing and saving without changing EU organization shows no warning.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Short title" with "Test Provided by in Assessment Report 2025 - Re-updated"
    Then I press "Update"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Re-updated has been updated."

  Scenario: Form validation should preserve selected organisation field values.
    Given I am logged in as an "authenticated user"
    And public_organisation assessments:
      | name | status    | organisation_type        | country |
      | Org1 | published | local_public_sector_body | Belgium |
    And I go to "/collection/assessments/report/create"

    When I fill in "Short title" with "Test Provided by in Assessment Report 2025"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I select "Org1" from "Select organisation"
    And press "Save"

    Then the option with text "Local public sector body" from select "Type of public sector body" is selected
    And the option with text "Belgium" from select "Country" is selected
    And the option with text "Org1" from select "Select organisation" is selected
