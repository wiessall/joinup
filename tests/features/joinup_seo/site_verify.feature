@api @group-g
Feature:
  Tests related to the site verifications.

  Scenario: Google search verification.
    When I go to "/google49574b354ced8b03.html"
    Then I should see the text "google-site-verification: google49574b354ced8b03.html"
