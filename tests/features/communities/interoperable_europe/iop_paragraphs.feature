@api
Feature:
  Access and functionality checks for the IOP related paragraphs.

  Scenario: Moderators can access the special paragraphs.
    Given collection:
      | title | Joinup    |
      | state | validated |

    # Mimic the "Interoperable Europe" collection.
    And collection:
      | uri   | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c |
      | title | Interoperable Europe                                           |
      | state | validated                                                      |

    Given I am logged in as a moderator
    And I go to the "Joinup" collection
    And I click "Add custom page"
    Then I should not see the button "Add Row"

    Given I am on the homepage
    And I click "Interoperable Europe" in the Header region
    And I click "Add custom page"
    # Fill in the title to avoid the CSS validation kicking in when clicking
    # buttons.
    And I fill in "Title" with "Hello world"
    Then I should see the button "Add Row"
    But I should not see the button "Add 1 column layout"
    And I should not see the button "Add 2 column layout"
    And I should not see the button "Add 2 column with featured media on the left"
    And I should not see the button "Add 2 column with featured media on the right"
    And I should not see the button "Add 3 column layout"
    And I should not see the button "Add Image"
    And I should not see the button "Add Call to action"
    And I should not see the button "Add Carousel"

    When I press "Add Row"
    Then I should see the button "Add 1 column layout"
    And I should see the button "Add 2 column layout"
    And I should see the button "Add 2 column with featured media on the left"
    And I should see the button "Add 2 column with featured media on the right"
    And I should see the button "Add 3 column layout"
    But I should not see the button "Add Image"
    And I should not see the button "Add Call to action"
    And the following fields should not be present "URL, Link text"
    When I press "Add 1 column layout"
    And I should see the button "Add Image"
    And I should see the button "Add Call to action"
    And I should see the button "Add Carousel"
    When I press "Add Carousel"
    Then the following fields should be present "URL, Link text"

    Given I am logged in as a facilitator of the "Joinup" collection
    And I go to the "Joinup" collection
    And I click "Add custom page"
    Then I should not see the button "Add Row"

    Given I am logged in as a facilitator of the "Interoperable Europe" collection
    And I am on the homepage
    And I click "Interoperable Europe" in the Header region
    And I click "Add custom page"
    Then I should not see the button "Add Row"
    But I should not see the button "Add 1 column layout"
    And I should not see the button "Add 2 column layout"
    And I should not see the button "Add 3 column layout"
