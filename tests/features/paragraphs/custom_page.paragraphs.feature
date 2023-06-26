@api @group-d
Feature:
  As a site builder of the site
  In order to be able to better control the structure of my content
  I need to be able to place paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |

  @javascript
  Scenario: Paragraph sections are multivalue and sort-able.
    Given custom_page content:
      | title           | collection            | state     |
      | Paragraphs page | Paragraphs collection | validated |
    And I append "simple_paragraph" to "field_paragraphs_body" field in "node" entity with following "Paragraphs page" title:
      | field_body:value  | AAAAAAAAAA      |
      | field_body:format | content_editor  |
    And I append "simple_paragraph" to "field_paragraphs_body" field in "node" entity with following "Paragraphs page" title:
      | field_body:value  | BBBBBBBBBB      |
      | field_body:format | content_editor  |
    Then I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the "Paragraphs page" custom page
    And I should see the following paragraphs in the given order:
      | AAAAAAAAAA |
      | BBBBBBBBBB |

    Then I open the header local tasks menu
    And I click "Edit"
    And I click "Move down" in the "Custom page body" for paragraph layout 1
    And I press "Save"
    Then there should be 2 paragraphs in the page
    And I should see the following paragraphs in the given order:
      | BBBBBBBBBB |
      | AAAAAAAAAA |

    Then I open the header local tasks menu
    And I click "Edit"
    And I click "Choose component"
    Then I should not see the link "Simple paragraph"

  @javascript
  Scenario: Privileged users can add a map and/or an iframe and map paragraph.
    Given users:
      | Username |
      | Zohan    |
    And the following collection user membership:
      | collection            | user  |
      | Paragraphs collection | Zohan |
    And custom_page content:
      | title                     | body        | collection            | author | state     |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs collection | Zohan  | validated |

    # Normal members cannot add maps.
    When I am logged in as "Zohan"
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should not see the link "Simple paragraph"
    And I should not see the link "Map"
    But I should not see the link "IFrame"


    # Facilitators can add maps.
    When I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should not see the link "Simple paragraph"
    And I should see the link "Map"
    But I should not see the link "IFrame"

    # Moderators can add maps.
    Given I am logged in as a moderator
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should not see the link "Simple paragraph"
    And I should see the link "Map"
    And I should see the link "IFrame"

    When I click "Map"
    # As the Webtools Map a webservice, we only test a fake JSON.
    And I fill in "JSON" with "{\"foo\":\"bar\"}"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And the response should contain "{\"foo\":\"bar\"}"

    When I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click the last "Choose component" link
    And I click "IFrame"
    And I fill in "Iframe URL" with "http://example.com"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"
    # We only test that the <iframe .../> element is in page. Unfortunately, we
    # cannot do precision RegExp search in page source, so we only check for the
    # existence of the iframe and its URL.
    Then I should see 1 "iframe" elements
    And the response should contain "src=\"http://example.com\""

  @javascript
  Scenario Outline: Add an accordion to the custom page.
    Given I am logged in as a <role>
    And I go to the "Paragraphs collection" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Paragraphs accordion page"
    Then the page should contain no paragraphs

    Given I click "Choose component"
    Then I click "Accordion"
    And press "Add Accordion item"
    And I wait for AJAX to finish

    Given I fill in the 1st "Item label" with "Super" in the "Accordion item" field
    And I enter "Clumsy text" in the 1st "Body" wysiwyg editor in the "Accordion item" field
    And I fill in the 2nd "Item label" with "Duper" in the "Accordion item" field
    And I enter "Crunchy text" in the 2nd "Body" wysiwyg editor in the "Accordion item" field
    And I press "Save" in the "Modal buttons" region
    And I press "Save"
    Then I should see the heading "Paragraphs accordion page"
    And I should see the text "Super"
    And I should see the text "Duper"
    But I should not see the text "Clumsy text"
    And I should not see the text "Crunchy text"

    Given I am not logged in
    And I go to the "Paragraphs accordion page" custom page

    And I press "Duper"
    # Wait for the animation to finish.
    And I wait for 2 second
    Then I should see the text "Crunchy text"
    But I should not see the text "Clumsy text"

    And I press "Super"
    # Wait for the animation to finish.
    And I wait for 2 second
    Then I should see the text "Clumsy text"
    But I should not see the text "Crunchy text"

    Examples:
      | role                                                  |
      | moderator                                             |
      | facilitator of the "Paragraphs collection" collection |
