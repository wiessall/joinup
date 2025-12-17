@api @group-e
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
    Then I should see the error message "3 errors have been found: Short titleType of public sector bodyBinding requirements"

    When I fill in "Short title" with "Test Assessment Report 2025"
    And I should see "This will be used as the title of the published report. Maximum 255 characters."

    And I should see "Union entity or public sector body which is providing the report"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Austrian Ministry of Digital Affairs"
    And I check "Check this box if assessment is linked to previous assessments or related assessments at EU or national level."
    And I fill in "Reference of the linked assessment" with "https://interoperable-europe.ec.europa.eu/report"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open
    And I press "Save" in the "Modal buttons" region
    And I should see the text "3 errors have been found: NameName of the initiativeDescription" in the "Modal content" region

    Then I should see the following lines of text in the correct order in "Modal content":
      | Initiatives                            |
      | General                                |
      | Trans-European digital public services |
      | Affected stakeholders                  |
      | Interoperability solutions             |
      | Documents                              |

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][documented_in][0][uri]" with "https://interoperable-europe.ec.europa.eu/initiatives" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region
    And I fill in "documented_in[0][uri]" with "https://interoperable-europe.ec.europa.eu/general" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I should see the text "Specify the trans-European digital public service(s) concerned" in the "Modal content" region
    And I should see the text "Specify the concerned function(s) of trans-European digital public service(s)" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    Then I should see the text "Public and/or private stakeholders affected" in the "Modal content" region
    And the page should contain the html text 'aria-placeholder="Select or start typing..."'
    When I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Interoperability solutions" in the "Modal content" region
    And I should see the text "Identify the interoperability solutions mandatory to use for the implementation of the assessed binding requirement" in the "Modal content" region
    And I should see the button "Select an Interoperability solution" in the "Modal content" region
    And I press "Add a new Interoperability solution" in the "Modal content" region
    And I fill in "asset[form][0][title][0][value]" with "European Interoperability Framework (EIF)" in the "Modal content" region
    And I enter "Description of European Interoperability Framework (EIF)" in the "asset[form][0][description][0][value]" wysiwyg editor
    And I fill in "Relevant website" with "https://interoperable-europe.ec.europa.eu/collection/eif" in the "Modal content" region
    Then I press "Add Interoperability solution" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Documents" in the "Modal content" region
    And I attach the file "text.pdf" to "Add a new file"
    And I should see "Maximum 5 files. 10 MB limit. Allowed types: txt doc docx pdf ppt pptx zip rar xls xlsx." in the "Modal content" region
    Then I press "Save" in the "Modal buttons" region

    # Identified effects on cross-border interoperability
    When I click "Legal" in the "Content" region
    And I should see "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border legal interoperability</span>"
    And I select the radio button "Beneficial" with the name "legal[0][inline_entity_form][interpretation]"
    And I fill in "legal[0][inline_entity_form][judgement][0][value]" with "Complies with GDPR and eIDAS regulations"

    When I click "Organisational" in the "Content" region
    And I should see "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border organisational interoperability</span>"
    And I select the radio button "Negligible" with the name "organisational[0][inline_entity_form][interpretation]"
    And I fill in "organisational[0][inline_entity_form][judgement][0][value]" with "Requires coordination between authorities"

    When I click "Semantic" in the "Content" region
    And I should see "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border semantic interoperability</span>"
    And I select the radio button "Beneficial" with the name "semantic[0][inline_entity_form][interpretation]"
    And I fill in "semantic[0][inline_entity_form][judgement][0][value]" with "Uses standardized Core Vocabularies"

    When I click "Technical" in the "Content" region
    And I should see "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border technical interoperability</span>"
    And I select the radio button "Beneficial" with the name "technical[0][inline_entity_form][interpretation]"
    And I fill in "technical[0][inline_entity_form][judgement][0][value]" with "Implements REST APIs and OpenAPI specs"

    When I click "Remaining barriers" in the "Content" region
    And I fill in "Have you discovered any remaining barriers to cross-border interoperability?" with "No significant barriers identified."

    # Assessment supporting documents
    And I attach the file "text.pdf" to "Add a new file"
    And I should see "Maximum 5 files. 10 MB limit. Allowed types: txt doc docx pdf ppt pptx zip rar xls xlsx."

    # Add comment
    And I fill in "Please add any other comment you would like to share." with "This assessment was conducted in collaboration with multiple stakeholders across EU member states."

    When I press "Save"
    Then I should see the success message containing "New assessment Test Assessment Report 2025 has been created."
    And I should see the heading "Test Assessment Report 2025"
    And I should see the following lines of text:
      | Austrian Ministry of Digital Affairs, a European Union institution from                            |
      | The report has been connected to the following previous/relevant reports                           |
      | https://interoperable-europe.ec.europa.eu/report                                                   |
      | documented in https://interoperable-europe.ec.europa.eu/general                                    |
      | This assessment was conducted in collaboration with multiple stakeholders across EU member states. |

    # Edit test.
    When I go to the edit form of the "Test Assessment Report 2025" assessment
    And I fill in "Short title" with "Test Assessment Report 2025 v2"
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

    When I fill in "Short title" with "Test Provided by in Assessment Report 2025"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "GR1"

    # Verify organization is NOT created when form has validation errors.
    # Regression test - organizations should only be created on successful save.
    Then I press "Save"
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
    And I fill in "Short title" with "Test Provided by in Assessment Report 2025 - Updated"
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
    And I fill in "Short title" with "Test Provided by in Assessment Report 2025 - Re-updated"
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Re-updated has been updated."

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I fill in "Short title" with "Test Provided by in Assessment Report 2025 - Re-updated"
    Then I press "…or add new"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "New EU Institution 2025"
    Then I press "…or select existing"
    And I wait for AJAX to finish
    Then I press "Save"
    Then I should not see the warning message containing "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
    And I should see the success message containing "Test Provided by in Assessment Report 2025 - Re-updated has been updated."

  @javascript
  Scenario: Form validation should preserve selected organisation field values.
    Given public_organisation assessments:
      | name | status    | organisation_type        | country |
      | Org1 | published | local_public_sector_body | Belgium |

    Given I am logged in as an "authenticated user"
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

  @javascript
  Scenario: Published assessments are viewable by anonymous and authenticated users
    Given user:
      | Username    | pol2025              |
      | First name  | Pol                  |
      | Family name | Gomez                |
      | E-mail      | pol.gomez@example.pt |

    And I am logged in as pol2025
    And I go to "/collection/assessments/report/create"

    When I fill in "Short title" with "Test permissions in Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "CTT PT"

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

    # Verify permissions after assigning the new role.
    Given I am logged in as a "moderator"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "Pol Gomez" row
    And I check the box "Assessment reporter"
    And I press "Save"
    Then I should see the message "The user has been notified that their account has been updated."

    And I am logged in as pol2025
    And I go to "/collection/assessments/report/create"
    Then I should see the button "Save as Published"

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

  @javascript
  Scenario: Advanced search
    Given public_organisation assessments:
      | name                  | organisation_type          |
      | Bluewave Technologies | european_union_institution |
    And report assessments:
      | name                 | provider              | state     | created              |
      | Initial Overview     | Bluewave Technologies | published | 2020-01-01T00:00:00Z |
      | Transmission Summary |                       | published | 2020-01-02T00:00:00Z |
      | Acme Report          |                       | published | 2020-01-03T00:00:00Z |
      | Risk Evaluation      |                       | review    | 2020-01-04T00:00:00Z |
      | Draft Observation    |                       | draft     | 2020-01-05T00:00:00Z |
    And I am an anonymous user
    When I visit the advanced search page
    And I check the "Assessment reports (3)" checkbox from the "Content types" facet
    Then the page should show only the tiles "Initial Overview, Transmission Summary, Acme Report"

    When I enter "Transmission" in the search bar and press enter
    Then the page should show only the tiles "Transmission Summary"
    When I enter "Bluewave" in the search bar and press enter
    Then the page should show only the tiles "Initial Overview"

  @javascript
  Scenario: Initiative name field accepts 256+ characters but expression title is truncated to 255
    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I fill in "Short title" with "Test Initiative Name Length"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Test Organisation"

    # Binding requirements with a very long initiative name (256+ characters)
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiatives" in the "Modal content" region
    # This is a 323-character string to test that initiative_name accepts more than 255 characters
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Comprehensive Overview and Strategic Analysis of System Architecture, Functional Requirements, Operational Constraints, Governance Models, Security and Compliance Requirements, Performance Optimization, and Long-Term Maintainability Considerations for Scalable Digital Platforms in Enterprise and Public-Sector Environments" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "Test description for initiative name length validation" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "Test Service" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    Then I press "Save"
    Then I should see the success message containing "New assessment Test Initiative Name Length has been created."
    And I should see the heading "Test Initiative Name Length"
    # Verify the initiative name is displayed (at least partially)
    And I should see the text "Comprehensive Overview and Strategic Analysis of System Architecture"
    # Verify that the expression entity title (name field) was truncated to exactly 255 characters
    # The 323-char initiative_name gets copied to the name field and truncated to 255 chars by the presave hook
    Then the "expression" assessment with name "Comprehensive Overview and Strategic Analysis of System Architecture, Functional Requirements, Operational Constraints, Governance Models, Security and Compliance Requirements, Performance Optimization, and Long-Term Maintainability Considerations for Sca" should exist
