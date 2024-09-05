# TODO: Remove this test in ISAICP-9053
@api @group-clone
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

    Given I visit "/dashboard"
    When I press "Disable EU OSS Catalogue"
    Then I should see the success message "EU OSS Catalogue has been disabled"

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

    Given I visit "/dashboard"
    When I press "Enable EU OSS Catalogue"
    Then I should see the success message "EU OSS Catalogue has been enabled"

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
