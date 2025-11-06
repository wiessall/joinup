@api @group-clone
Feature: Test ISAICP-10437

  @update:joinup_taxonomy_deploy_200700
  Scenario: Check that country code and EU status were imported.
    Given I am on the homepage
    Then the France country code should be FR
    And France is member of the European Union
    And the Japan country code should be JP
    But Japan is not member of the European Union
