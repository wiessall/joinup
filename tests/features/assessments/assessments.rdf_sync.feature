@api
Feature: RDF Sync integration for assessments.

  Scenario: Test that an entire chain of entities is exported properly.
    Given "participation" assessment:
      | name          | stakeholder_country | participant_type         |
      | Stakeholder 1 | Belgium             | local_public_sector_body |
    And "public_service" assessment:
      | name             | identifier | thematic_area                      |
      | Public Service 1 | PS-001     | core_governance_and_administration |
    And "public_organisation" assessment:
      | name                  | organisation_type          | country |
      | Public Organisation 1 | european_union_institution | Belgium |
    And "expression" assessment:
      | name         | description   | documented_in       |
      | Expression 1 | Description 1 | https://example.com |
    And the following "binding_requirement" paragraph:
      | id             | 999              |
      | description    | BR Description 1 |
      | expression     | Expression 1     |
      | affects        | Public Service 1 |
      | affected_party | Stakeholder 1    |
    And the following assessment statements:
      | id  | statement_type | interpretation | judgement |
      | 999 | legal          | positive       | Compliant |
    And "report" assessment:
      | name     | binding_requirement | results_in | provider              |
      | Report 1 | 999                 | 999        | Public Organisation 1 |
