@api @group-e
Feature: Assessment Report Entity Management
  In order to manage assessment reports directly
  As a user with appropriate permissions
  I need to be able to create, edit, and delete assessment report entities

  Scenario: Anonymous users cannot access assessment report creation
    Given I am an anonymous user
    When I go to "/assessments/add/report"
    Then I should see the heading "Sign in to continue"

  Scenario: Authenticated users without permissions cannot access assessment report creation
    Given I am logged in as an "authenticated user"
    When I go to "/assessments/add/report"
    Then I should see the heading "Access denied"
    And the response status code should be 403

  @javascript
  Scenario: Create a new assessment report with required fields
    Given I am logged in as a developer
    And I go to "/assessments/add/report"

    When I press "Save"
    Then I should see the error message "5 errors have been found: NameType of public sector bodyOrganisation nameDescriptionBinding requirements"

    When I fill in "Name" with "Test Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I select "Austria" from "Country"
    And I fill in "Organisation name" with "Austrian Ministry of Digital Affairs"
    And I fill in "Description" with "The Austrian Ministry responsible for digital transformation and e-government initiatives"
    And I select the radio button "Yes"
    And I fill in "Relevant assessment report" with "https://interoperable-europe.ec.europa.eu/report"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open
    And I press "Save" in the "Modal buttons" region
    And I should see the text "5 errors have been found: NameDescriptionNameDescriptionDescription" in the "Modal content" region

    When I click "General" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region
    And I fill in "documented_in[0][uri]" with "https://interoperable-europe.ec.europa.eu/general" in the "Modal content" region

    When I click "Initiatives" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "This initiative focuses on digital transformation across public services" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][documented_in][0][uri]" with "https://interoperable-europe.ec.europa.eu/initiatives" in the "Modal content" region

    When I click "Interoperability solutions" in the "Modal content" region
    And I press "Add new relevant asset" in the "Modal content" region
    And I fill in "asset[form][0][name][0][value]" with "European Interoperability Framework (EIF)" in the "Modal content" region
    And I fill in "asset[form][0][landing_page][0][uri]" with "https://interoperable-europe.ec.europa.eu/collection/eif" in the "Modal content" region
    Then I press "Create relevant asset" in the "Modal content" region

    When I click "Trans-European digital public services" in the "Modal content" region
    And I fill in "has_related_service[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region
    And I fill in "has_related_service[0][inline_entity_form][description][0][value]" with "Cross-border authentication service for European citizens" in the "Modal content" region
    And I select "Education and Research" from "has_related_service[0][inline_entity_form][thematic_area][]" in the "Modal content" region

    When I click "Affected stakeholders" in the "Modal content" region
    And I select "Local public sector body" from "affected_party[0][inline_entity_form][participant_type][]" in the "Modal content" region
    And I select "Belgium" from "affected_party[0][inline_entity_form][stakeholder_country][]" in the "Modal content" region

    When I click "Documents" in the "Modal content" region
    And I attach the file "empty_pdf.pdf" to "Add a new file" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region

    # Identified effects on cross-border interoperability
    When I click "Legal" in the "Content" region
    And I select "1" from "legal_interoperability[0][inline_entity_form][interpretation]"
    And I fill in "legal_interoperability[0][inline_entity_form][judgement][0][value]" with "Complies with GDPR and eIDAS regulations"
    And I attach the file "empty_pdf.pdf" to "edit-legal-interoperability-0-inline-entity-form-landing-page-0-upload"

    When I click "Organisational" in the "Content" region
    And I select "0" from "organisational_interoperability[0][inline_entity_form][interpretation]"
    And I fill in "organisational_interoperability[0][inline_entity_form][judgement][0][value]" with "Requires coordination between authorities"
    And I attach the file "empty_pdf.pdf" to "edit-organisational-interoperability-0-inline-entity-form-landing-page-0-upload"

    When I click "Semantic" in the "Content" region
    And I select "1" from "semantic_interoperability[0][inline_entity_form][interpretation]"
    And I fill in "semantic_interoperability[0][inline_entity_form][judgement][0][value]" with "Uses standardized Core Vocabularies"
    And I attach the file "empty_pdf.pdf" to "edit-semantic-interoperability-0-inline-entity-form-landing-page-0-upload"

    When I click "Technical" in the "Content" region
    And I select "1" from "technical_interoperability[0][inline_entity_form][interpretation]"
    And I fill in "technical_interoperability[0][inline_entity_form][judgement][0][value]" with "Implements REST APIs and OpenAPI specs"
    And I attach the file "empty_pdf.pdf" to "edit-technical-interoperability-0-inline-entity-form-landing-page-0-upload"

    When I click "Other" in the "Content" region
    And I select "-1" from "other_interoperability[0][inline_entity_form][interpretation]"
    And I fill in "other_interoperability[0][inline_entity_form][judgement][0][value]" with "Language barriers may present challenges"
    And I attach the file "empty_pdf.pdf" to "edit-other-interoperability-0-inline-entity-form-landing-page-0-upload"

    # Assessment supporting documents
    And I attach the file "empty_pdf.pdf" to "edit-documents-0-upload"

    When I press "Save"
    Then I should see the success message containing "New assessment Test Assessment Report 2025 has been created."
    And I should see the heading "Test Assessment Report 2025"
