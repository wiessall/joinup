@api
Feature: RDF Sync integration for assessments.

  Scenario: Test that an entire chain of entities is exported properly.
    Given "participation" assessment:
      | uuid     | name             | stakeholder_country | participant_type         |
      | p-uuid-1 | Public Service 1 | Belgium             | local_public_sector_body |
    And "public_service" assessment:
      | uuid      | name             | identifier | thematic_area                      |
      | ps-uuid-1 | Public Service 1 | PS-001     | core_governance_and_administration |
    And "public_organisation" assessment:
      | uuid      | name                  | organisation_type          | country |
      | po-uuid-1 | Public Organisation 1 | european_union_institution | Belgium |
    And "expression" assessment:
      | uuid     | name         | description   | documented_in       |
      | e-uuid-1 | Expression 1 | Description 1 | https://example.com |
    And the following binding requirement:
      | uuid | description      | expression | public_service | participation |
      | br-1 | BR Description 1 | e-uuid-1   | ps-uuid-1      | p-uuid-1      |
    And the following assessment statements:
      | uuid | type  | interpretation | judgement |
      | st-1 | legal | positive       | Compliant |
    And "report" assessment:
      | uuid     | name     | binding_requirement | results_in |
      | r-uuid-1 | Report 1 | br-1                | st-1       |
