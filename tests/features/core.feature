@api @group-d
Feature: Various tests related to the server.

  Scenario: Ensure that CLI and Drupal use the same timezone.
    Then cli should use the same timezone as Drupal
