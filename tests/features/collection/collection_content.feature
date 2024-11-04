@api @group-a
Feature: Collection content
  As a user of the website
  I want to access the content of a collection
  So that I can find the information I'm looking for.

  Background:
    Given collection content:
      | title                     | state     |
      | Turin Egyptian Collection | published |
    And solution content:
      | title                       | collection                | state     | creation date    | modification date | topic |
      | Tomb Of Unknown Restoration | Turin Egyptian Collection | published | 2017-11-11T11:11 | 2017-11-30T11:11  | HR    |
    And discussion content:
      | title                                 | body                                                                    | state     | collection                | topic                                                 | created          | changed                   |
      | Bigger than Egyptian Museum of Cairo? | <p><a href="#link">Link to the museum</a> web<strong>site</strong>.</p> | published | Turin Egyptian Collection | Finance in EU, Supplier exchange, E-health, E-justice | 2017-11-11T11:11 | Wed, 25 Dec 2019 15:00:00 |
    And document content:
      | title           | field_paragraphs_body                            | state     | collection                | creation date    | changed          | topic    |
      | Upper Floor Map | <p>A sample <a href="#link">map</a> example.</p> | published | Turin Egyptian Collection | 2017-11-11T11:11 | 2017-11-30T11:11 | E-health |
    And event content:
      | title                                     | state     | collection                | start date          | end date            | topic             |
      | Opening of the Hellenistic Period Section | published | Turin Egyptian Collection | 2016-03-15T11:12:12 | 2016-03-15T11:12:12 | Supplier exchange |
    And news content:
      | title                          | body                                                           | state     | collection                | changed    | topic             |
      | Turin Egyptian Museum Reopened | <p>After <em>more than</em> <a href="#link">two years</a>.</p> | published | Turin Egyptian Collection | 2019-11-19 | Supplier exchange |

  Scenario: Publishing new solutions should result in counters being properly updated.
    Given owner content:
      | name             | owner type |
      | Particle sweeper | Company    |

    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4436
    Given I am logged in as a moderator
    And I go to the homepage of the "Turin Egyptian Collection" collection

    # The solution counters do not include the unpublished solutions.
    Then I should see the link "1 Solution" in the "Content" region
    And I see the text "1 Solution" in the "Header" region

    When I click "Add solution" in the plus button menu
    And I check "I have read and accept the legal notice and I commit to manage my solution on a regular basis."
    And I press "Yes"
    And I fill in the following:
      | Title                 | Solution from draft to published                                    |
      | Abstract              | The abstract of the solution.                                       |
      | Description           | Testing that publishing a solution, updates the collection content. |
      | Geographical coverage | Belgium                                                             |
      | Name                  | Costas Papazoglou                                                   |
      | E-mail address        | CostasPapazoglou@example.com                                        |
    And I select "Data gathering and processing" from "Topic"
    And I select "Logging Service" from "Solution type"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Particle sweeper"
    And I press "Add owner"
    And I press "Publish"
    Then I should see the heading "Solution from draft to published"

    When I go to the homepage of the "Turin Egyptian Collection" collection
    # Since there are 2 solutions, the link moved in first since it is the type of content with most items.
    # When the facet tab is displayed, the text is "@count Solutions", while if it is in the "More" dropdown, it shows
    # as "Solutions (@count)".
    Then I see the text "2 Solutions" in the "Header" region
    And I should see the link "2 Solutions"

    # Create a draft version of the solution to verify that counters are not affected.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4436.
    When I go to the homepage of the "Solution from draft to published" solution
    And I click "Edit" in the "Entity actions" region
    And I fill in "Title" with "Solution from draft to published but draft"
    And I press "Save as draft"
    Then I should see the heading "Solution from draft to published"

    When I go to the homepage of the "Turin Egyptian Collection" collection
    Then I see the text "2 Solution" in the "Header" region
    And I should see the link "2 Solutions"
    Then I delete the "Solution from draft to published" solution
    And I delete the "Costas Papazoglou" contact information

  @javascript
  Scenario: When I subscribe to a collection, the corresponding tile is shown in my profile
    Given I am logged in as a moderator
    When I go to the homepage of the "Turin Egyptian Collection" collection
    And I press "Join this collection"
    And I press "Confirm" in the "Modal buttons" region
    And I wait for AJAX to finish
    Then I go to "/user"
    # Assert that the "Collection" tile is present and contains number of members, label and bundle type.
    Then I should see the "Turin Egyptian Collection" tile
    And I should see the statistics "1" in the "Turin Egyptian Collection" tile
    And I should see the text "Collection" in the "Turin Egyptian Collection" tile

  Scenario: Content type facet labels should show the plural form when multiple results are available.
    Given I go to the homepage of the "Turin Egyptian Collection" collection
    Then the "Discussion" content tab is not displayed
    And the "Document" content tab is displayed
    And the "Event" content tab is displayed
    And I should see the link "1 News"
    And I should see the link "1 Solution"

    Given solution content:
      | title                | collection                | state     |
      | Protecting Artifacts | Turin Egyptian Collection | published |
    And discussion content:
      | title                              | state     | collection                |
      | Is the entrance free for children? | published | Turin Egyptian Collection |
    And document content:
      | title                   | state     | collection                |
      | Fire Safety Regulations | published | Turin Egyptian Collection |
    And event content:
      | title                        | state     | collection                |
      | Cleopatra Jewelry Exhibition | published | Turin Egyptian Collection |
    And news content:
      | title                                  | state     | collection                |
      | Museum Temporary Closed Next Wednesday | published | Turin Egyptian Collection |

    Given I reload the page
    Then the "Discussions" content tab is not displayed
    And the "Documents" content tab is displayed
    And the "Events" content tab is displayed
    And I should see the link "2 News"
    And I should see the link "2 Solutions"

    Then I visit "/collection/turin-egyptian-collection?f=&page=9"
    When the response status code should be 200

  @javascript
  Scenario: Links and markup should be stripped from tiles summary.
    Given I go to the homepage of the "Turin Egyptian Collection" collection
    # Check the solution tile.
    Then I should see the "Tomb Of Unknown Restoration" tile
    And I should see the text "Last update: 30/11/2017" in the "Tomb Of Unknown Restoration" tile
    But I should not see the text "Last update: 11/11/2017" in the "Tomb Of Unknown Restoration" tile
    And I should see the link "Tomb Of Unknown Restoration" in the "Tomb Of Unknown Restoration" tile
    And I should see the text "HR" in the "Tomb Of Unknown Restoration" tile
    And I should see the text "Solution" in the "Tomb Of Unknown Restoration" tile
    # Check the document tile.
    And I should see the "Upper Floor Map" tile
    And I should see the text "Last update: 30/11/2017" in the "Upper Floor Map" tile
    And I should see the link "Upper Floor Map" in the "Upper Floor Map" tile
    And I should see the text "E-health" in the "Upper Floor Map" tile
    And I should see the text "Document" in the "Upper Floor Map" tile
    # As from tiles redesign (ISAICP-7666), do not show the body anymore.
    And I should not see the text "A sample map example."
    And I should not see the link "map"
    # Check the event tile.
    And I should see the "Opening of the Hellenistic Period Section" tile
    And I should see the text "15/03/2016" in the "Opening of the Hellenistic Period Section" tile
    And I should see the text "Virtual event" in the "Opening of the Hellenistic Period Section" tile
    And I should see the link "Opening of the Hellenistic Period Section" in the "Opening of the Hellenistic Period Section" tile
    And I should see the text "Supplier exchange" in the "Opening of the Hellenistic Period Section" tile
    And I should see the text "Event" in the "Opening of the Hellenistic Period Section" tile
    # Check the news tile.
    And I should see the "Turin Egyptian Museum Reopened" tile
    And I should see the text "Last update: 19/11/2019" in the "Turin Egyptian Museum Reopened" tile
    And I should see the link "Turin Egyptian Museum Reopened" in the "Turin Egyptian Museum Reopened" tile
    And I should not see the text "After more than two years." in the "Turin Egyptian Museum Reopened" tile
    And I should see the text "Supplier exchange" in the "Turin Egyptian Museum Reopened" tile
    And I should see the text "News" in the "Turin Egyptian Museum Reopened" tile

    # Check the discussion tile.
    When I go to the discussions page of "Turin Egyptian Collection"
    Then I should see the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the text "Last activity: 25/12/2019, 15:00" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the link "Bigger than Egyptian Museum of Cairo?" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the text "Finance in EU" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the text "Supplier exchange" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the text "+2 topics" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the statistics "0" in the "Bigger than Egyptian Museum of Cairo?" tile
    And I should see the text "Discussion" in the "Bigger than Egyptian Museum of Cairo?" tile
