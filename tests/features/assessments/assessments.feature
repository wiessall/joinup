@api @group-e @assessments
Feature: Assessment Report Entity Management
  In order to manage assessment reports directly
  As a user with appropriate permissions
  I need to be able to create, edit, and delete assessment report entities

  @javascript
  Scenario: Create a new assessment report with required fields
    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I press "Save"
    Then I should see the error message "3 errors have been found: NameType of public sector bodyBinding requirements"

    When I fill in "Name" with "Test Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Austrian Ministry of Digital Affairs"
    And I select the radio button "Yes"
    And I fill in "Relevant assessment report" with "https://interoperable-europe.ec.europa.eu/report"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open
    And I press "Save" in the "Modal buttons" region
    And I should see the text "4 errors have been found: NameName of the initiativeDescriptionDescription" in the "Modal content" region

    Then I should see the following lines of text in the correct order in "Modal content":
      | Initiatives                            |
      | General                                |
      | Trans-European digital public services |
      | Affected stakeholders                  |
      | Interoperability solutions             |
      | Documents                              |

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "This initiative focuses on digital transformation across public services" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][documented_in][0][uri]" with "https://interoperable-europe.ec.europa.eu/initiatives" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region
    And I fill in "documented_in[0][uri]" with "https://interoperable-europe.ec.europa.eu/general" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Interoperability solutions" in the "Modal content" region
    And I press "Add new relevant solution" in the "Modal content" region
    And I fill in "asset[form][0][title][0][value]" with "European Interoperability Framework (EIF)" in the "Modal content" region
    And I enter "Description of European Interoperability Framework (EIF)" in the "asset[form][0][description][0][value]" wysiwyg editor
    And I fill in "Relevant website" with "https://interoperable-europe.ec.europa.eu/collection/eif" in the "Modal content" region
    Then I press "Create relevant solution" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area]" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Documents" in the "Modal content" region
    And I attach the file "text.pdf" to "Add a new file"

    Then I press "Save" in the "Modal buttons" region

    # Identified effects on cross-border interoperability
    When I click "Legal" in the "Content" region
    And I select the radio button "Beneficial" with the name "legal[0][inline_entity_form][interpretation]"
    And I fill in "legal[0][inline_entity_form][judgement][0][value]" with "Complies with GDPR and eIDAS regulations"

    When I click "Organisational" in the "Content" region
    And I select the radio button "Negligible" with the name "organisational[0][inline_entity_form][interpretation]"
    And I fill in "organisational[0][inline_entity_form][judgement][0][value]" with "Requires coordination between authorities"

    When I click "Semantic" in the "Content" region
    And I select the radio button "Beneficial" with the name "semantic[0][inline_entity_form][interpretation]"
    And I fill in "semantic[0][inline_entity_form][judgement][0][value]" with "Uses standardized Core Vocabularies"

    When I click "Technical" in the "Content" region
    And I select the radio button "Beneficial" with the name "technical[0][inline_entity_form][interpretation]"
    And I fill in "technical[0][inline_entity_form][judgement][0][value]" with "Implements REST APIs and OpenAPI specs"

    When I click "Other" in the "Content" region
    And I select the radio button "Risky" with the name "other[0][inline_entity_form][interpretation]"
    And I fill in "other[0][inline_entity_form][judgement][0][value]" with "Language barriers may present challenges"

    # Assessment supporting documents
    And I attach the file "text.pdf" to "Add a new file"

    When I press "Save"
    Then I should see the success message containing "New assessment Test Assessment Report 2025 has been created."
    And I should see the heading "Test Assessment Report 2025"
    And I should see the following lines of text:
      | Austrian Ministry of Digital Affairs, a European Union institution from  |
      | The report has been connected to the following previous/relevant reports |
      | https://interoperable-europe.ec.europa.eu/report                         |
      | documented in https://interoperable-europe.ec.europa.eu/general          |

    # Edit test.
    When I go to the edit form of the "Test Assessment Report 2025" assessment
    And I fill in "Name" with "Test Assessment Report 2025 v2"
    And I press "Save"
    Then I should see the heading "Test Assessment Report 2025 v2"

    # Delete test.
    Given I am logged in as a "assessment_reporter"
    When I go to the delete form of the "Test Assessment Report 2025 v2" assessment
    Then I should see the heading "Are you sure you want to delete the assessment Test Assessment Report 2025 v2?"
    When I press "Delete"
    Then I should see the success message containing "The assessment Test Assessment Report 2025 v2 has been deleted."

  @javascript
  Scenario: All possible tests for 'Provided by' plugin in assessment report
    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"

    When I fill in "Name" with "Test Provided by in Assessment Report 2025"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "GR1"
    And I select the radio button "No"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "This initiative focuses on digital transformation across public services" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    # Save Assessment Report.
    Then I press "Save"
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
    And the following field should not be present "Select organisation"
    And I fill in "New organisation name" with "Portugal Ministry of Digital Affairs"
    Then I press "Save"

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Spain Ministry of Digital Affairs"
    Then I press "…or select existing"
    And I wait for AJAX to finish
    And the option with text "Portugal Ministry of Digital Affairs" from select "Select organisation" is selected

    # Change to local and change back to original state.
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "New organisation name"
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
    And the option with text "- Select -" from select "Select organisation" is selected
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
    Then I press "Save"
    Then I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    Then the public_organisation assessment "Organisation Should Not Be Created" should not exist

    # Test case-insensitive matching for local organizations.
    # Regression test for case-insensitive name matching.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "gr1"
    Then I press "Save"
    Then I should see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test case-insensitive matching for EU organizations.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "portugal ministry of digital affairs"
    Then I press "Save"
    Then I should see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
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
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test that a second NEW organization with different name is also created without warnings.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Another New Belgian Organisation 2025"
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 has been updated."

    # Test that editing and saving without changing local organization shows no warning.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Name" with "Test Provided by in Assessment Report 2025 - Updated"
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Updated has been updated."

    # Test that NEW EU organizations are created without warnings.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "New EU Institution 2025"
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Updated has been updated."

    # Test that editing and saving without changing EU organization shows no warning.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Name" with "Test Provided by in Assessment Report 2025 - Re-updated"
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Re-updated has been updated."

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Name" with "Test Provided by in Assessment Report 2025 - Re-updated"
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "New EU Institution 2025"
    Then I press "…or select existing"
    And I wait for AJAX to finish
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Re-updated has been updated."

  @javascript
  Scenario: Published assessments are viewable by anonymous and authenticated users
    Given user:
      | Username    | pol2025              |
      | First name  | Pol                  |
      | Family name | Gomez                |
      | E-mail      | pol.gomez@example.pt |

    And I am logged in as pol2025
    And I go to "/collection/assessments/report/create"

    When I fill in "Name" with "Test permissions in Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "CTT PT"
    And I select the radio button "No"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "This initiative focuses on digital transformation across public services" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    Then I press "Save as In Review"
    Then I should see the success message containing "New assessment Test permissions in Assessment Report 2025 has been created."
    And I should see the heading "Test permissions in Assessment Report 2025"

    # Check draft state.
    Given I am an anonymous user
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should not see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as an "authenticated user"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Access denied"

    Given I am logged in as a "assessment_reporter"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as a "moderator"
    When I go to the edit form of the "Test permissions in Assessment Report 2025" assessment
    And I press "Save as Published"
    Then I should see the success message containing "The assessment Test permissions in Assessment Report 2025 has been updated."

    # Check draft state.
    Given I am an anonymous user
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as an "authenticated user"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as a "assessment_reporter"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

  Scenario: Test assessment reports views
    Given the assessments collection exists

    # Test as anonymous.
    When I am not logged in
    And I visit "collection/assessments/report/repository"
    Then I should see the heading "Assessment reports"
    And I should not see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the text "There are no published assessment reports yet."
    Given report assessments:
      | name                        | state     |
      | Weekly Health Check         | published |
      | Portal Accessibility Review | review    |
      | System Audit Draft          | draft     |
    And I reload the page
    And the page should show only the tiles "Weekly Health Check"
    When I go to "/user/assessments"
    Then I should see the heading "Sign in to continue"

    # Test as logged in user.
    Given users:
      | Username         |
      | Lorian Nightfall |
    And I am logged in as "Lorian Nightfall"
    And I visit "collection/assessments/report/repository"
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should see the link "My assessments"
    And the page should show only the tiles "Weekly Health Check"
    When I follow "My assessments"
    Then I should be on "/user/assessments"
    Then I should see the heading "My assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the text "There are no assessment reports yet for the current user."
    Given report assessments:
      | name                       | state     | uid              |
      | Alpha Release Summary      | published | Lorian Nightfall |
      | Beta Feature Review        | review    | Lorian Nightfall |
      | Prototype Evaluation Draft | draft     | Lorian Nightfall |
    And I reload the page
    And the page should show only the tiles "Alpha Release Summary, Beta Feature Review, Prototype Evaluation Draft"

    # Ensure the reports view only applies to the assessment collection.
    When I am not logged in
    And I go to "/collection/portal-support/report/repository"
    Then I should get a 404 HTTP response
