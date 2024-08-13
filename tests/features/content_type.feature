@api @content_type @group-i
Feature:
  As the administrator of content types
  I should be able to add a new content type through Drupal UI.

  Scenario:
    Given I am logged in as a user with the "administer content types, administer node fields, access administration pages, view the administration theme" permissions
    When I visit "/admin/structure/types"
    And the HTML title of the page should be "Content types"
    And I should see the link "Add content type"

    When I click "Add content type"
    Then I should get a 200 HTTP response
    And I should see the heading "Add content type"
    And the HTML title of the page should be "Add content type"

    When I fill in "Name" with "New content type"
    And I fill in "Machine-readable name" with "new_test_content_type"

    # If we ever disable Field UI, button will be "Save content type" and user
    # should be redirected to "/admin/structure/types".
    And I press "Save and manage fields"
    Then the relative url should be "/admin/structure/types/manage/new_test_content_type/fields"
    And the HTML title of the page should be "Manage fields"
