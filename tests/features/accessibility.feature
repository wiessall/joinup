@api @group-clone @javascript
Feature: Accessibility

  # TODO: This test should be extended:
  # - With as much relevant pages. At least we should cover pages fixed in the
  #   past as effect of received accessibility reports.
  # - Test also as authenticated user
  Scenario: Test accessibility

    Given I am on homepage
    Then the page meets at least moderate accessibility standards
