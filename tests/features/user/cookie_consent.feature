@api @group-e @cookieConsent @antispam
Feature: Cookie consent
  In order to be compliant with the ePrivacy directive
  As a product owner
  I want to offer the possibility for users to reject the use of cookies

  Scenario: Check that CCK widget is in page.
    Given I am an anonymous user
    And I am on homepage
    Then the response should contain "<script type=\"application/json\">{\"utility\":\"cck\",\"url\":\"https:\/\/ec.europa.eu\/info\/cookies_{lang}\"}</script>"
