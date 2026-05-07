@api @group-c @javascript
Feature: Assessment Report Entity Management
  In order to manage assessment reports directly
  As a user with appropriate permissions
  I need to be able to create, edit, and delete assessment report entities

  Scenario: Create a new assessment report with required fields
    Given the following "solution" content:
      | title         | landing page           |
      | Test solution | http://www.example.com |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I press "Save as draft"
    Then I should see the error message "3 errors have been found: Short title Type of public sector body Identification of binding requirement(s) assessed"

    When I fill in "Short title" with "Test Assessment Report 2025"
    And I should see the text "This will be used as the title of the published report. Maximum 255 characters."

    And I should see the text "Union entity or public sector body which is providing the report"
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
    And I should see the text "4 errors have been found: Name Name of the initiative Description Description of the binding requirement" in the "Modal content" region

    Then I should see the following lines of text in the correct order in "Modal content":
      | Initiative                |
      | Requirements              |
      | Concerned public services |
      | Affected stakeholders     |
      | Interoperability solution |
      | Supporting document       |

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][documented_in][0][uri]" with "https://interoperable-europe.ec.europa.eu/initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region
    And I fill in "documented_in[0][uri]" with "https://interoperable-europe.ec.europa.eu/general" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I should see the text "Specify the trans-European digital public service(s) concerned" in the "Modal content" region
    And I should see the text "Specify the concerned function(s) of trans-European digital public service(s)" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "General public services" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region
    And I select "Luxembourg" from "affects[0][inline_entity_form][countries][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    Then I should see the text "Public and/or private stakeholders affected" in the "Modal content" region
    And the page should contain the html text 'aria-placeholder="Select or start typing..."'
    When I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    And I should not see the text "Drag to re-order taxonomy terms." in the "Modal content" region

    When I click "Interoperability solution" in the "Modal content" region
    # Select an existing solution via entity browser.
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    And I select "Test solution" in the entity browser
    And I press "Select solutions" to close the entity browser
    And I should see the text "Test solution" in the "Modal content" region
    And I should see the button "Add Interoperability Solution" in the "Modal content" region
    # Create a new solution via entity browser.
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    And I click "New solution"
    And I wait until ".ck-editor__editable" element exists
    When I press "Create" in the entity browser
    Then I should see the error message "This value should not be null."
    And I fill in "Title of Solution" with "European Interoperability Framework (EIF)"
    And I enter "Description of EIF" in the "Description" wysiwyg editor
    And I fill in "Relevant website" with "http://no-abstract.com"
    And I press "Create" to close the entity browser
    And I should see the text "European Interoperability Framework (EIF)" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I select "General public services" from "affects[0][inline_entity_form][thematic_area][]" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I should see the text "Check this box to select all European Union countries" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I check "Check this box to select all European Union countries"
    Then I should not see the text "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region
    Then I uncheck "Check this box to select all European Union countries"
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Supporting document" in the "Modal content" region
    And I attach the file "text.pdf" to "Add a new file"
    And I should see the text "Maximum 5 files. 10 MB limit. Allowed types: txt doc docx pdf ppt pptx zip rar xls xlsx." in the "Modal content" region
    Then I press "Save" in the "Modal buttons" region

    # Identified effects on cross-border interoperability
    When I click "Legal" in the "Content" region
    Then I should not see the text "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border legal interoperability</span>"
    When I select the radio button "N/A" with the name "legal[0][inline_entity_form][interpretation]"
    Then I should not see the text "Provide a justification"
    When I select the radio button "Beneficial" with the name "legal[0][inline_entity_form][interpretation]"
    Then I should see the text "Provide a justification"
    And I fill in "legal[0][inline_entity_form][judgement][0][value]" with "Complies with GDPR and eIDAS regulations"

    When I click "Organisational" in the "Content" region
    Then I should not see the text "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border organisational interoperability</span>"
    When I select the radio button "Negligible" with the name "organisational[0][inline_entity_form][interpretation]"
    Then I should see the text "Provide a justification"
    And I fill in "organisational[0][inline_entity_form][judgement][0][value]" with "Requires coordination between authorities"

    When I click "Semantic" in the "Content" region
    Then I should not see the text "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border semantic interoperability</span>"
    When I select the radio button "Beneficial" with the name "semantic[0][inline_entity_form][interpretation]"
    Then I should see the text "Provide a justification"
    And I fill in "semantic[0][inline_entity_form][judgement][0][value]" with "Uses standardized Core Vocabularies"

    When I click "Technical" in the "Content" region
    Then I should not see the text "Provide a justification"
    And the response should contain "<span class=\"visually-hidden fieldset-legend fw-bold\">Identified effects on cross-border technical interoperability</span>"
    # Fill in a judgement value to verify it gets cleared on N/A.
    When I select the radio button "Beneficial" with the name "technical[0][inline_entity_form][interpretation]"
    Then I should see the text "Provide a justification"
    And I fill in "technical[0][inline_entity_form][judgement][0][value]" with "Implements REST APIs and OpenAPI specs"
    # Selecting N/A should hide and clear the judgement field.
    When I select the radio button "N/A" with the name "technical[0][inline_entity_form][interpretation]"
    Then I should not see the text "Provide a justification"
    # Switching back to Beneficial should show the preserved judgement value.
    When I select the radio button "Beneficial" with the name "technical[0][inline_entity_form][interpretation]"
    Then I should see the text "Provide a justification"
    And the "technical[0][inline_entity_form][judgement][0][value]" field should contain "Implements REST APIs and OpenAPI specs"

    And I should see the text "Describe any remaining barriers to cross-border interoperability discovered."
    And I fill in "Remaining barriers to cross-border interoperability" with "No significant barriers identified."

    # Assessment supporting documents
    And I attach the file "text.pdf" to "Add a new file"
    And I should see the text "Maximum 5 files. 10 MB limit. Allowed types: txt doc docx pdf ppt pptx zip rar xls xlsx."

    # Add comment
    And I fill in "Please add any other comment you would like to share." with "This assessment was conducted in collaboration with multiple stakeholders across EU member states."

    When I press "Save as draft"
    Then I should see the success message containing "New assessment Test Assessment Report 2025 has been created."
    And I should see the heading "Test Assessment Report 2025"
    And I should see the following lines of text:
      # General information
      | Status: Draft                                                                                      |
      | Austrian Ministry of Digital Affairs, a Local public sector body from Belgium                      |
      | The report has been connected to the following previous/relevant reports                           |
      | https://interoperable-europe.ec.europa.eu/report                                                   |
      # Binding requirements
      | Digital Transformation Initiative                                                                  |
      | This is the general binding requirement description                                                |
      | eIDAS Authentication Service                                                                       |
      | Local public sector body                                                                           |
      | Test solution                                                                                      |
      # Results
      | Complies with GDPR and eIDAS regulations                                                           |
      | Requires coordination between authorities                                                          |
      | Uses standardized Core Vocabularies                                                                |
      | Implements REST APIs and OpenAPI specs                                                             |
      | No significant barriers identified.                                                                |
      # Supporting documents and general comments
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
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Comprehensive Overview and Strategic Analysis of System Architecture, Functional Requirements, Operational Constraints, Governance Models, Security and Compliance Requirements, Performance Optimization, and Long-Term Maintainability Considerations for Scalable Digital Platforms in Enterprise and Public-Sector Environments" in the "Modal content" region
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

  # Regression test for ISAICP-10806: Empty participation entities are created
  Scenario: Empty participation entities should not be created when affected stakeholders section is left empty
    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I fill in "Short title" with "Test Empty Participation ISAICP-10806"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Test Organisation"

    # Add binding requirement with required fields but WITHOUT filling "Affected stakeholders"
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Test Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "Test Service" in the "Modal content" region

    # Fill description last to avoid losing value during tab switching
    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "Test requirement description" in the "Modal content" region

    # Press Save directly without clicking other tabs
    Then I press "Save" in the "Modal buttons" region
    And the modal should be closed

    When I press "Save as draft"
    Then I should see the success message containing "New assessment Test Empty Participation ISAICP-10806 has been created."

    # Verify that no empty participation entities were created
    Then no participation entities should exist for the "Test Empty Participation ISAICP-10806" assessment report

  Scenario: User can browse only published solutions in binding requirement
    Given the following "solution" content:
      | title                          | state       | interoperable |
      | Test solution published        | published   |               |
      | Test solution archived         | archived    |               |
      | Test solution proposed         | proposed    |               |
      | Test solution draft            | draft       |               |
      | Test solution unpublished      | unpublished |               |
      | Test interoperable solution    | published   | yes           |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    And I click "New binding requirement"
    When I click "Interoperability solution" in the "Modal content" region
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    Then I should not see the text "Interoperability Solutions Browser"
    And I should see the text "Test solution published"
    And I should see the text "Test interoperable solution"
    And I should not see the text "Test solution archived"
    And I should not see the text "Test solution proposed"
    And I should not see the text "Test solution draft"
    And I should not see the text "Test solution unpublished"

  Scenario: Entity browser exposed filters: name, domain and interoperable filters
    Given the following "solution" content:
      | title              | state     | domain           | interoperable | description                             | keywords           |
      | Alpha Framework    | published | Defense          | yes           | A robust integration tool               | games              |
      | Beta Platform      | published | Economic affairs |               | A platform for data exchange             | nintendo64 licences |
      | Gamma Service      | published | Defense          | yes           | Service for robust identity verification | games              |
      | Delta Application  | published | Economic affairs |               | Application for document management      | nintendo64 licences |
      | Robust Engine      | published | Defense          |               | A simple computation engine              | games              |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    And I click "New binding requirement"
    When I click "Interoperability solution" in the "Modal content" region
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser

    Then I should see the text "Alpha Framework"
    And I should see the text "Beta Platform"
    And I should see the text "Gamma Service"
    And I should see the text "Delta Application"
    And I should see the text "Robust Engine"

    When I fill in "Name" with "Alpha"
    And I press "Search"
    And I wait for AJAX to finish
    Then I should see the text "Alpha Framework"
    And I should not see the text "Beta Platform"
    And I should not see the text "Gamma Service"
    And I should not see the text "Delta Application"
    And I should not see the text "Robust Engine"

    When I fill in "Name" with "robust"
    And I press "Search"
    And I wait for AJAX to finish
    Then I should see the text "Alpha Framework"
    And I should not see the text "Beta Platform"
    And I should see the text "Gamma Service"
    And I should not see the text "Delta Application"
    And I should see the text "Robust Engine"

    When I fill in "Name" with "nintendo64"
    And I press "Search"
    And I wait for AJAX to finish
    Then I should not see the text "Alpha Framework"
    And I should see the text "Beta Platform"
    And I should not see the text "Gamma Service"
    And I should see the text "Delta Application"
    And I should not see the text "Robust Engine"

    When I fill in "Name" with ""
    And I press "Search"
    And I wait for AJAX to finish
    And I select "Defense" from "Domain"
    And I wait for AJAX to finish
    Then I should see the text "Alpha Framework"
    And I should see the text "Gamma Service"
    And I should see the text "Robust Engine"
    And I should not see the text "Beta Platform"
    And I should not see the text "Delta Application"
    And the "Domain" field should contain the "- Any domain -" option

    When I select "- Any domain -" from "Domain"
    And I wait for AJAX to finish
    And I check "Interoperable Europe solutions"
    And I wait for AJAX to finish
    Then I should see the text "Alpha Framework"
    And I should see the text "Gamma Service"
    And I should not see the text "Beta Platform"
    And I should not see the text "Delta Application"
    And I should not see the text "Robust Engine"

    When I uncheck "Interoperable Europe solutions"
    And I wait for AJAX to finish
    Then I should see the text "Alpha Framework"
    And I should see the text "Beta Platform"
    And I should see the text "Gamma Service"
    And I should see the text "Delta Application"
    And I should see the text "Robust Engine"

  Scenario: Entity browser: add existing asset, create new asset, and remove asset
    Given the following "solution" content:
      | title                | landing page           |
      | Asset to be removed  | http://www.example.com |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    Then I should see the heading "Create assessment report"

    When I fill in "Short title" with "Test Entity Browser Remove Asset"
    And I select "Local public sector body" from "Type of public sector body"
    And I wait for AJAX to finish
    And I select "Belgium" from "Country"
    And I wait for AJAX to finish
    And I fill in "New organisation name" with "Test Organisation"

    When I click "New binding requirement"
    And a modal should open

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Test Initiative for EB" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "Test requirement description" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "Test Service" in the "Modal content" region

    When I click "Interoperability solution" in the "Modal content" region
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    And I select "Asset to be removed" in the entity browser
    And I press "Select solutions" to close the entity browser
    And I should see the text "Asset to be removed" in the "Modal content" region

    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    And I click "New solution"
    And I wait until ".ck-editor__editable" element exists
    And I press "Create" in the entity browser
    And I should see the error message "This value should not be null."
    And I wait until ".ck-editor__editable" element exists
    And I fill in "Title of Solution" with "Asset that should remain"
    And I enter "Description of the asset" in the "Description" wysiwyg editor
    And I fill in "Relevant website" with "http://remaining-asset.com"
    And I press "Create" to close the entity browser
    And I should see the text "Asset that should remain" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    And the modal should be closed
    When I press "Save as draft"
    Then I should see the success message containing "New assessment Test Entity Browser Remove Asset has been created."
    And I should see the text "Asset to be removed"
    And I should see the text "Asset that should remain"

    When I go to the edit form of the "Test Entity Browser Remove Asset" assessment
    And I click on element ".lpb-edit"
    And a modal should open

    When I click "Interoperability solution" in the "Modal content" region
    And I should see the text "Asset to be removed" in the "Modal content" region
    And I should see the text "Asset that should remain" in the "Modal content" region

    # Verify the new solution appears in the entity browser table.
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    Then I should see the text "Asset that should remain"
    And I press "Select solutions" to close the entity browser

    When I remove "Asset to be removed" from the entity browser widget
    And I wait for AJAX to finish
    Then I should not see the text "Asset to be removed" in the "Modal content" region
    And I should see the text "Asset that should remain" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    And the modal should be closed
    When I press "Update"
    Then I should see the success message containing "The assessment Test Entity Browser Remove Asset has been updated."
    And I should see the text "Asset that should remain"
    And I should not see the text "Asset to be removed"

  Scenario: Description is shown as fallback when solution abstract is empty in entity browser
    Given the following "solution" content:
      | title                     | state     | abstract        | description        |
      | Solution with abstract    | published | Abstract text   | Hidden description |
      | Solution without abstract | published |                 | Fallback shown     |

    Given I am logged in as an "authenticated user"
    And I go to "/collection/assessments/report/create"
    And I click "New binding requirement"
    When I click "Interoperability solution" in the "Modal content" region
    And I press "Add Interoperability Solution" in the "Modal content" region to open the entity browser
    # Solution with abstract: abstract is shown, description is not.
    Then I should see the text "Abstract text"
    And I should not see the text "Hidden description"
    # Solution without abstract: description is shown as fallback.
    And I should see the text "Fallback shown"

  # Regression test for the empty ID after duplication.
  # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-11248
  Scenario: Duplicating a binding requirement and editing the copy opens the modal
    Given I am logged in as a moderator
    And I go to "/collection/assessments/report/create"

    When I click "New binding requirement"
    And a modal should open

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Duplicate Test Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "Test Service" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "Test binding requirement description" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "has_participation[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "has_participation[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    And the modal should be closed

    When I click on element ".lpb-duplicate"
    And I wait for AJAX to finish

    # Click the second edit link by finding the first component and then
    # getting the first sibling which is the duplicated component.
    When I click on element ".js-lpb-component ~ .js-lpb-component .lpb-edit"
    Then a modal should open
