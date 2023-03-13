@api @group-clone
Feature: Covers Release 1.91.

  Scenario: Test that no duplicated confirmations exist.
    # @todo Remove this step entirely in the next release cleanup.
    Given no multiple confirmed confirmations exist for the same email
