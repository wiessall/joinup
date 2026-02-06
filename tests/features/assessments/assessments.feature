@api @group-c
Feature: Assessment Report Entity Management
  In order to manage assessment reports directly
  As a user with appropriate permissions
  I need to be able to create, edit, and delete assessment report entities

  @javascript
  Scenario: Create a new assessment report with required fields
    Given solution content:
      | title         | landing page           |
      | Test solution | http://www.example.com |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I press "Save as draft"
    Then I should see the error message "3 errors have been found: Short titleType of public sector bodyIdentification of binding requirement(s) assessed"

    When I fill in "Short title" with "Test Assessment Report 2025"
    And I should see "This will be used as the title of the published report. Maximum 255 characters."

    And I should see "Union entity or public sector body which is providing the report"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    Then I should not see the text "New organisation name"
    When I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Austrian Ministry of Digital Affairs"
    And I check "Check this box if assessment is linked to previous assessments or related assessments at EU or national level."
    And I fill in "Reference of the linked assessment" with "https://interoperable-europe.ec.europa.eu/report"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open
    And I should see the button "Cancel" in the "Modal buttons" region
    And I press "Save" in the "Modal buttons" region
    And I should see the text "4 errors have been found: NameName of the initiativeDescriptionDescription of the binding requirement" in the "Modal content" region

    Then I should see the following lines of text in the correct order in "Modal content":
      | Initiative                |
      | Requirements              |
      | Concerned public services |
      | Affected stakeholders     |
      | Interoperability solution |
      | Supporting document       |

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][documented_in][0][uri]" with "https://interoperable-europe.ec.europa.eu/initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region
    And I fill in "documented_in[0][uri]" with "https://interoperable-europe.ec.europa.eu/general" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I should see the text "Specify the trans-European digital public service(s) concerned" in the "Modal content" region
    And I should see the text "Specify the concerned function(s) of trans-European digital public service(s)" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region
    And I select "Luxembourg" from "affects[0][inline_entity_form][countries][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    Then I should see the text "Public and/or private stakeholders affected" in the "Modal content" region
    And the page should contain the html text 'aria-placeholder="Select or start typing..."'
    When I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Interoperability solution" in the "Modal content" region
    Then I press "Select an Interoperability solution" in the "Modal content" region
    And I type "Test" in the "asset[form][0][entity_id]" autocomplete field
    And I pick "Test solution" from the autocomplete suggestions
    And I press "Add Interoperability solution" in the "Modal content" region
    And I should see the text "Identify the interoperability solutions mandatory to use for the implementation of the assessed binding requirement" in the "Modal content" region
    And I should see the button "Select an Interoperability solution" in the "Modal content" region
    And I press "Add a new Interoperability solution" in the "Modal content" region
    And I fill in "Title of Solution" with "European Interoperability Framework (EIF)" in the "Modal content" region
    And I enter "Description of EIF" in the "asset[form][1][description][0][value]" wysiwyg editor
    And I fill in "Relevant website" with "http://no-abstract.com" in the "Modal content" region
    Then I press "Add Interoperability solution" in the "Modal content" region
    Then the "IEF table" table should contain the following columns:
      | Title                                     | Relevant website       | Description        |
      | Test solution                             | http://www.example.com | default abstract   |
      | European Interoperability Framework (EIF) | http://no-abstract.com | Description of EIF |

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "Core governance and administration" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I should see "Check this box to select all European Union countries" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I check "Check this box to select all European Union countries"
    Then I should not see "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    Then I uncheck "Check this box to select all European Union countries"
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Supporting document" in the "Modal content" region
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

    And I should see "Describe any remaining barriers to cross-border interoperability discovered."
    And I fill in "Remaining barriers to cross-border interoperability" with "No significant barriers identified."

    # Assessment supporting documents
    And I attach the file "text.pdf" to "Add a new file"
    And I should see "Maximum 5 files. 10 MB limit. Allowed types: txt doc docx pdf ppt pptx zip rar xls xlsx."

    # Add comment
    And I fill in "Please add any other comment you would like to share." with "This assessment was conducted in collaboration with multiple stakeholders across EU member states."

    When I press "Save as draft"
    Then I should see the success message containing "New assessment Test Assessment Report 2025 has been created."
    And I should see the heading "Test Assessment Report 2025"
    And I should see the following lines of text:
      | Status: Draft                                                                                      |
      | Austrian Ministry of Digital Affairs, a Local public sector body from Belgium                      |
      | The report has been connected to the following previous/relevant reports                           |
      | https://interoperable-europe.ec.europa.eu/report                                                   |
      | This assessment was conducted in collaboration with multiple stakeholders across EU member states. |

    # Edit test.
    When I go to the edit form of the "Test Assessment Report 2025" assessment
    And I fill in "Short title" with "Test Assessment Report 2025 v2"
    And I press "Submit"
    Then I should see the heading "Test Assessment Report 2025 v2"
    And I should see the text "Status: In Review"

    # Delete test.
    Given I am logged in as a user with the "Moderator" role
    When I go to the delete form of the "Test Assessment Report 2025 v2" assessment
    Then I should see the heading "Are you sure you want to delete the assessment Test Assessment Report 2025 v2?"
    When I press "Delete"
    Then I should see the success message containing "The assessment Test Assessment Report 2025 v2 has been deleted."

  @javascript
  Scenario: Initiative name field accepts 256+ characters but expression title is truncated to 255
    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I fill in "Short title" with "Test Initiative Name Length"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Test Organisation"

    # Binding requirements with a very long initiative name (256+ characters)
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiative" in the "Modal content" region
    # This is a 323-character string to test that initiative_name accepts more than 255 characters
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Comprehensive Overview and Strategic Analysis of System Architecture, Functional Requirements, Operational Constraints, Governance Models, Security and Compliance Requirements, Performance Optimization, and Long-Term Maintainability Considerations for Scalable Digital Platforms in Enterprise and Public-Sector Environments" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "Test description for initiative name length validation" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
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

  Scenario: Check taxonomy term page canonical access
    # Check access restrictions applied by rabbit_hole.
    When I go to the "Beneficial" assess_statement_interpretation term page
    Then I should see the heading "Sign in to continue"
    When I am logged in as an "authenticated user"
    When I go to the "Beneficial" assess_statement_interpretation term page
    Then the response status code should be 403
