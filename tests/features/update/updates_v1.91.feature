@api @group-clone
Feature: Covers Release 1.91.

  Scenario: Test that no duplicated confirmations exist.
    # @todo Remove this step entirely in the next release cleanup.
    Given no multiple confirmed confirmations exist for the same email

  Scenario: The 'About' group link should be highlighted.
    # This collection was buggy before update.
    Given I visit the "FOSSEPS - Free and Open Source Software Solutions for European Public Services" collection
    And I click "About"
    Then the "About" group menu link should be active
