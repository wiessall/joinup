@api @group-j
Feature: Translating assessments

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
    And public_organisation assessments:
      | name        | organisation_type          |
      | ACME        | european_union_institution |
    And the following assessment statements:
      | id  | statement_type | interpretation | judgement             |
      | 900 | legal          | neutral        | Needs more assessment |
    And "public_service" assessment:
      | name             | identifier | thematic_area           |
      | Public Service 1 | PS-001     | general_public_services |
    And "expression" assessment:
      | initiative_name | description   | documented_in       |
      | Expression 1    | Description 1 | https://example.com |
    And the following "binding_requirement" paragraph:
      | id                | 999               |
      | description       | BR Description 1  |
      | expression        | Expression 1      |
      | affects           | Public Service 1  |
    And report assessment:
      | name     | provider | binding_requirement | state     | results_in |
      | Report 1 | ACME     | 999                 | published | 900        |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" report assessment
    Then the response status code should be <code>

    Examples:
      | user        | title    | code |
      | Flora Mod   | Report 1 | 200  |
      | Lee Facil   | Report 1 | 403  |

  @javascript
  Scenario: Test creating assessment report translation from UI.
    Given I am logged in as "Flora Mod"

    # Assert language select options/value.
    When I go to the edit form of the "Report 1" assessment
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Report 1" assessment
    When I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Report 1"
    Then the following fields should be present "Short title, Identification of binding requirement(s) assessed, Remaining barriers to cross-border interoperability, Please add any other comment you would like to share."
    Then the following fields should not be present "Language, Union entity or public sector body which is providing the report, Assessment supporting documents"
    When I fill in "Short title" with "Raport 1"
    And I fill in "Remaining barriers to cross-border interoperability" with "Nie zidentyfikowano żadnych istotnych barier."
    And I fill in "Please add any other comment you would like to share." with "Ocena została przeprowadzona we współpracy z wieloma interesariuszami."

    # Binding requirements
    When I click "Edit"
    And a modal should open
    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][value]" with "Expression 1 PL" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Description 1 PL" in the "Modal content" region
    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "BR Description 1 PL" in the "Modal content" region
    Then I press "Save" in the "Modal buttons" region

    And I press "Save"
    Then I should see the success message "The assessment Raport 1 has been updated."
    And I should see the heading "Raport 1"
    And I should see the following lines of text:
      | Raport 1                                                                |
      | Nie zidentyfikowano żadnych istotnych barier.                           |
      | Ocena została przeprowadzona we współpracy z wieloma interesariuszami.  |
      | Expression 1 PL                                                         |
      | Description 1 PL                                                        |
      | BR Description 1 PL                                                     |

    # Edit translation.
    When I open the header local tasks menu
    When I click "Translate" in the "Entity actions" region
    Then I should see "Raport 1"
    When I click "Edit" in the "Polish" row
    And I fill in "Short title" with "Zaktualizowana ocena"
    And I press "Save"
    Then I should see the success message "The assessment Zaktualizowana ocena has been updated."
    And I should not see the heading "Raport 1"
    And I should see the heading "Zaktualizowana ocena"

    # Delete translation.
    When I open the header local tasks menu
    When I click "Translate" in the "Entity actions" region
    And I click the "Delete" link in the dropdown menu in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the assessment Zaktualizowana ocena?"
    When I press "Delete Polish translation"
    Then I should see the success message "The assessment Zaktualizowana ocena Polish translation has been deleted."

  Scenario: Default language of new assessment should be same as language of the interface
    Given I am logged in as "Flora Mod"
    When I go to "/collection/assessments/report/create"
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    When I switch to the "pl" language
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "pl"
