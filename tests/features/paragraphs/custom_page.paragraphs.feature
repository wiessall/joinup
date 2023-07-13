@api @group-d
Feature:
  As a site builder of the site
  In order to be able to better control the structure of my content
  I need to be able to place paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |

  Scenario: Heading paragraph is displayed as heading element.
    Given custom_page content:
      | title                | collection            | state     |
      | Heading example page | Paragraphs collection | published |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H2 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h2";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H3 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h3";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H4 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h4";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H5 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h5";}} |

    When I go to the "Heading example page" custom page
    Then I should see an "h2" element with the text "H2 heading paragraph" in the "Content" region
    And I should see an "h3" element with the text "H3 heading paragraph" in the "Content" region
    And I should see an "h4" element with the text "H4 heading paragraph" in the "Content" region
    And I should see an "h5" element with the text "H5 heading paragraph" in the "Content" region

  @javascript
  Scenario: User should be able to edit behaviour settings.
    Given custom_page content:
      | title                  | collection            | state     |
      | Behaviour example page | Paragraphs collection | published |

    When I am logged in as a moderator
    And I go to the edit form of the "Behaviour example page" "custom page"
    And I click "Choose component"
    And I click "Heading"
    And I should see the text "Settings" in the "Modal content" region

    Then I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Behaviour example page" "custom page"
    And I click "Choose component"
    And I click "Heading"
    And I should see the text "Settings" in the "Modal content" region

  @javascript
  Scenario: Paragraph sections are multivalue and sort-able.
    Given custom_page content:
      | title           | collection            | state     |
      | Paragraphs page | Paragraphs collection | published |
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
    And I press "Update"
    Then there should be 2 paragraphs in the page
    And I should see the following paragraphs in the given order:
      | BBBBBBBBBB |
      | AAAAAAAAAA |

    Then I open the header local tasks menu
    And I click "Edit"
    And I click "Choose component"
    Then I should not see the link "Simple paragraph"

  @javascript
  Scenario Outline: Privileged users can add paragraphs.
    Given the following <group type>:
      | title | Paragraphs group type |
      | state | validated             |
    And users:
      | Username |
      | Zohan    |
      | Gilfoyle |
    And the following <group type> user membership:
      | <group type>          | user     | roles       |
      | Paragraphs group type | Zohan    | author      |
      | Paragraphs group type | Gilfoyle | facilitator |
    And custom_page content:
      | title                     | body        | <group type>          | author | state     |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs group type | Zohan  | published |

    # Author.
    When I am logged in as "Zohan"
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should see the link "Layout"
    And I should see the link "Accordion"
    And I should see the link "Call to action"
    And I should see the link "Carousel"
    And I should see the link "Content listing"
    But I should not see the link "Entity reference"
    And I should not see the link "Github feed"
    And I should see the link "Heading"
    But I should not see the link "IFrame"
    And I should see the link "Image"
    And I should not see the link "Map"
    And I should see the link "Quote"
    But I should not see the link "Row"
    But I should not see the link "Simple paragraph"
    And I should see the link "Testimony"
    And I should see the link "Text"
    And I should see the link "Video"

    # Facilitator.
    When I am logged in as "Gilfoyle"
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should see the link "Layout"
    And I should see the link "Accordion"
    And I should see the link "Call to action"
    And I should see the link "Carousel"
    And I should see the link "Content listing"
    But I should not see the link "Entity reference"
    And I should see the link "Github feed"
    And I should see the link "Heading"
    But I should not see the link "IFrame"
    And I should see the link "Image"
    And I should not see the link "Map"
    And I should see the link "Quote"
    But I should not see the link "Row"
    But I should not see the link "Simple paragraph"
    And I should see the link "Testimony"
    And I should see the link "Text"
    And I should see the link "Video"

    # Moderator.
    Given I am logged in as a moderator
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    Then I should see the link "Layout"
    And I should see the link "Accordion"
    And I should see the link "Call to action"
    And I should see the link "Carousel"
    And I should see the link "Content listing"
    And I should see the link "Entity reference"
    And I should see the link "Github feed"
    And I should see the link "Heading"
    And I should see the link "IFrame"
    And I should see the link "Image"
    And I should see the link "Map"
    And I should see the link "Quote"
    But I should not see the link "Row"
    But I should not see the link "Simple paragraph"
    And I should see the link "Testimony"
    And I should see the link "Text"
    And I should see the link "Video"

    But I should not see the link "Row"
    And I should not see the link "1 column layout"
    And I should not see the link "2 column layout"
    And I should not see the link "2 column with featured media on the left"
    And I should not see the link "2 column with featured media on the right"
    And I should not see the link "3 column layout"
    And I should not see the link "Configurable listing"

    Examples:
      | group type |
      | collection |
      | solution   |

  @javascript
  Scenario: Moderator can manage map and iframe paragraph.
    Given users:
      | Username |
      | Zohan    |
    And the following collection user membership:
      | collection            | user  |
      | Paragraphs collection | Zohan |
    And custom_page content:
      | title                     | body        | collection            | author | state     |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs collection | Zohan  | published |

    Given I am logged in as a moderator
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    And I click "Map"
    # As the Webtools Map a webservice, we only test a fake JSON.
    And I fill in "JSON" with "{\"foo\":\"bar\"}"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And the response should contain "{\"foo\":\"bar\"}"

    When I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click the last "Choose component" link
    And I click "IFrame"
    And I fill in "Iframe URL" with "http://example.com"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
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
    And I press "Publish"
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

  Scenario: View quote paragraph in the custom page.
    Given custom_page content:
      | title          | collection            | state     |
      | Popular quotes | Paragraphs collection | published |
    And I append "quote" to "field_paragraphs_body" field in "node" entity with following "Popular Quotes" title:
      | paragraph_quote | That's one small step for a man, a giant leap for mankind. |

    When I am an anonymous user
    And I visit the "Popular quotes" custom page
    Then the page should contain the html text "That's one small step for a man, a giant leap for mankind."
