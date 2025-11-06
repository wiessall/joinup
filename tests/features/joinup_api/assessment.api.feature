@api @group-e
Feature: Assessments API

  Scenario: Use API for assessment.
    Given asset assessments:
      | name |
      | A 1  |
    And public_organisation assessments:
      | name | description   | organisation_type          |
      | PO 1 | Description 1 | european_union_institution |
    When I am on "/api/assessments/v1/asset"
    Then I should see the text '"name":"A 1"'
    But I should not see the text '"name":"PO 1"'
    When I am on "/api/assessments/v1/public-organisation"
    Then I should see the text '"name":"PO 1"'
    And I should see the text '"description":"Description 1","organisation_type":"european_union_institution"'
