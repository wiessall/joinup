# TODO: Re-enable in ISAICP-9052
@wip @api @group-clone
Feature: Test EU OSS Catalogue killswitch

  Scenario: Switch ON/OFF

    When I go to "/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200

    Given I am logged in as a "developer"
    When I go to "/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue/oss-solutions"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue/log"
    Then the response status code should be 200

    Given I disable EU OSS Catalogue

    Given I am an anonymous user
    When I go to "/eu-oss-catalogue"
    Then I should see the heading "Sign in to continue"
    And I go to "/eu-oss-catalogue/solutions"
    Then I should see the heading "Sign in to continue"

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 403
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 403

    Given I am logged in as a "developer"
    When I go to "/eu-oss-catalogue"
    Then the response status code should be 403
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue/oss-solutions"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue/log"
    Then the response status code should be 403

    Given I enable EU OSS Catalogue

    Given I am an anonymous user
    When I go to "/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200

    Given I am logged in as a "developer"
    When I go to "/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue/oss-solutions"
    Then the response status code should be 200
    And I go to "/admin/content/eu-oss-catalogue/log"
    Then the response status code should be 200
