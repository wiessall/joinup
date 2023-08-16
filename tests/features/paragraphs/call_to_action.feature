@api @group-a
Feature:
  As a site builder of the site
  I need to be able to use "Call to action" paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |

  @javascript
  Scenario: Call to action has alignment setting.
    Given custom_page content:
      | title               | collection            | state     |
      | Call to action page | Paragraphs collection | published |

    Then I am logged in as a moderator
    And I go to the edit form of the "Call to action page" "custom page"
    And I click "Choose component"
    And I click "Call to action"
    And I fill in "URL" with "https://european-union.europa.eu" in the "Modal content" region
    And I fill in "Link text" with "european-union.europa.eu" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "Right" from "Alignment" in the "Modal content" region
    And I select "Light blue" from "Color" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the page should contain the html text '<div class="paragraph--call-to-action d-flex justify-content-end paragraph">'
    When the page should contain the html text '<a href="https://european-union.europa.eu" class="btn btn-secondary">european-union.europa.eu</a>'
