@api
Feature: Test the publiccode.yml editor.

  Scenario: As an anonymous user I have access to the publiccode.yml editor.

    Given I am an anonymous user
    Then I visit "/publiccode-editor"
