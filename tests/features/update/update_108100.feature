@group-clone @api
Feature: Test joinup_core_update_108100().

  Scenario: The 'About' group link should be highlighted.

    # This collection was buggy before update.
    Given I visit the "FOSSEPS - Free and Open Source Software Solutions for European Public Services" collection
    And I click "About"
    Then the "About" group menu link should be active
