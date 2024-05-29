@api @group-clone
Feature: Ensure contacts were migrated and exist.

  Scenario: Smoke test for the contact information.
    Given I go to "/collection/joinup"
    # Normally, we would check for a link but since the dump is sanitized, the
    # email confirmation is not present in a rebuilt site.
    Then I should see the text "Joinup Support Team"
