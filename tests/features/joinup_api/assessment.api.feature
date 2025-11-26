@api @group-e
Feature: Assessments API

  Scenario: Use API for assessment.
    Given asset assessments:
      | name | landing_page       |
      | A 1  | http://example.com |
    And public_organisation assessments:
      | name | organisation_type          |
      | PO 1 | european_union_institution |
      | PO 2 | european_union_body        |
    When I am on "/api/assessments/v1/asset"
    Then I should see the text '"name":"A 1","landing_page":"http:\/\/example.com"'
    But I should not see the text '"name":"PO 1"'
    When I am on "/api/assessments/v1/public-organisation"
    Then I should see the text '"name":"PO 1"'
    And I should see the text '"description":"Description 1","organisation_type":"european_union_institution"'
    And I should see the text '"description":"","organisation_type":"european_union_body"'
