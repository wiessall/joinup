@api @group-clone
Feature: Test updates of ISAICP-8957.

  @update:joinup_core_deploy_200501
  Scenario: Test that results did not change in Search API Field paragraphs.
    Given I go to "/collection/open-source-observatory-osor/ospos-oss-governance"
    Then I should see the following headings:
      | Join the OSOR Webinar : Exploring the role of OSPOs in transforming public services with open source |
      | Trinidad and Tobago will open an Open Source Program Office                                          |
      | OSPOs for Good 2024 Highlights                                                                       |
