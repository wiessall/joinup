# TODO: Remove this test in ISAICP-9053
@api @group-clone
Feature: Test EU OSS Catalogue killswitch

  Scenario: Switch ON/OFF
    Given oss_solution content:
      | title                  | oss_development_status | oss_source        | oss_categories     | oss_short_description | status    |
      | The Opensource Panacea | development            | developers_italia | content-management | The cure              | published |

    Given I am an anonymous user
    When I go to "/eu-oss-catalogue"
    Then I should see the heading "Sign in to continue"
    And I go to "/eu-oss-catalogue/solutions"
    Then I should see the heading "Sign in to continue"
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then I should see the heading "Sign in to continue"
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 403
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 403
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 403
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"

    # Developers are admin users, this they have 'bypass node access'
    # permission. As an effect, they're able to access the node but not the
    # other routes.
    Given I am logged in as a "developer"
    When I go to "/eu-oss-catalogue"
    # Developer is admin role, they can still see the node.
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue/oss-solutions"
    Then the response status code should be 403
    And I go to "/admin/content/eu-oss-catalogue/log"
    Then the response status code should be 403
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 200
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"

    Given I visit "/dashboard"
    When I press "Enable EU OSS Catalogue"
    Then I should see the success message "EU OSS Catalogue has been enabled"

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
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 200
    When I am on the homepage
    Then I should see "EU Open Source Solutions Catalogue"

    Given I am an anonymous user
    When I go to "/eu-oss-catalogue"
    Then the response status code should be 200
    But I should not see "Sign in to continue"
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200
    But I should not see "Sign in to continue"
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 200
    But I should not see "Sign in to continue"
    When I am on the homepage
    Then I should see "EU Open Source Solutions Catalogue"

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 200
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 200
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 200
    When I am on the homepage
    Then I should see "EU Open Source Solutions Catalogue"

    Given I am logged in as a "developer"
    When I visit "/dashboard"
    And I press "Disable EU OSS Catalogue"
    Then I should see the success message "EU OSS Catalogue has been disabled"

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
    # Developer is and amin role, they can still see the node.
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 200
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"

    Given I am an anonymous user
    When I go to "/eu-oss-catalogue"
    Then I should see the heading "Sign in to continue"
    And I go to "/eu-oss-catalogue/solutions"
    Then I should see the heading "Sign in to continue"
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then I should see the heading "Sign in to continue"
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"

    Given I am logged in as an "authenticated"
    When I go to "/eu-oss-catalogue"
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    Then the response status code should be 403
    And I go to "/eu-oss-catalogue/solutions"
    Then the response status code should be 403
    And I go to the content page of the type "oss_solution" with the title "The Opensource Panacea"
    Then the response status code should be 403
    When I am on the homepage
    Then I should not see "EU Open Source Solutions Catalogue"
