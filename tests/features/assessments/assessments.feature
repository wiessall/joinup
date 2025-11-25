@api @group-e
Feature: Assessment Report Entity Management
  In order to manage assessment reports directly
  As a user with appropriate permissions
  I need to be able to create, edit, and delete assessment report entities

  @javascript
  Scenario: Create a new assessment report with required fields
    Given I am logged in as a developer
    And I go to "/assessments/add/report"
    Then I should see the heading "Create assessment report"

    When I press "Save"
    Then I should see the error message "3 errors have been found: NameType of public sector bodyBinding requirements"

    When I fill in "Name" with "Test Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And I fill in "Organisation name" with "Austrian Ministry of Digital Affairs"
    And I select the radio button "Yes"
    And I fill in "Relevant assessment report" with "https://interoperable-europe.ec.europa.eu/report"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open
    And I press "Save" in the "Modal buttons" region
    And I should see the text "4 errors have been found: NameNameDescriptionDescription" in the "Modal content" region

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

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "affected_party[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "affected_party[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Interoperability solutions" in the "Modal content" region
    And I press "Add new relevant asset" in the "Modal content" region
    And I fill in "asset[form][0][name][0][value]" with "European Interoperability Framework (EIF)" in the "Modal content" region
    And I fill in "asset[form][0][landing_page][0][uri]" with "https://interoperable-europe.ec.europa.eu/collection/eif" in the "Modal content" region
    Then I press "Create relevant asset" in the "Modal content" region

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
    When I go to the delete form of the "Test Assessment Report 2025 v2" assessment
    Then I should see the heading "Are you sure you want to delete the assessment Test Assessment Report 2025 v2?"
    When I press "Delete"
    Then I should see the success message containing "The assessment Test Assessment Report 2025 v2 has been deleted."

  @javascript
  Scenario: All possible tests for 'Provided by' plugin in assessment report
    Given I am logged in as a developer
    And I go to "/assessments/add/report"

    When I fill in "Name" with "Test Provided by in Assessment Report 2025"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And I fill in "Organisation name" with "GR1"
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
    And I fill in "has_related_service[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region

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
    And the following field should be present "Organisation name"
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"

    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And the following field should not be present "Select organisation"
    And I fill in "Organisation name" with "Portugal Ministry of Digital Affairs"
    Then I press "Save"

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I press "Add new organisation"
    And I wait for AJAX to finish
    And I fill in "Organisation name" with "Spain Ministry of Digital Affairs"
    Then I press "Select existing organisation"
    And I wait for AJAX to finish
    And the option with text "Portugal Ministry of Digital Affairs" from select "Select organisation" is selected

    # Change to local and change back to original state.
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Organisation name"
    And I select "Greece" from "Country"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
    And the option with text "- Select -" from select "Select organisation" is selected
    Then I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    And the following field should be present "Select organisation"
