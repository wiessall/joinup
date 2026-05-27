@api @group-clone
Feature: Migrate oss_features field from string (255) to string_long.

  @update:eu_oss_catalogue_update_201300
  Scenario: OSS solution features are preserved after field type migration.
    When I go to "/eu-oss-catalogue/solutions/pleio"
    Then I should see the text "Collaboration groups"
    And I should see the text "Document sharing"
    And I should see the text "Blogs and wikis"

    When I go to "/eu-oss-catalogue/solutions/netxms"
    Then I should see the text "Automatic network discovery and topology mapping"
    And I should see the text "SNMP monitoring (all versions) with vendor-specific device drivers"
    And I should see the text "Fine-grained access control and audit logging"
