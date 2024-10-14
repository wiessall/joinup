@group-d
Feature:
  As an owner of a website
  When I visit non existing pages
  I should get a 'Page not found' without exceptions

  Scenario: Accessing a non existing page, should show a custom 404 page.
    When I go to "/this-url-does-not-exist"
    Then I should see the heading "Page not found"
    And I should see the heading "404 error"
    And I should see the text "The page you are looking for does not exist; it may have been moved, or removed altogether. You might want to try the"
    And I should see the link "search function"
    And I should see the link "home page"

  @api
  Scenario: Accessing the external URL as path should raise a 404.
    When I go to "/https://joinup.ec.europa.eu/security.txt"
    Then I should see the heading "Not Found"
