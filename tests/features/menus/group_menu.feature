@api @group-b
Feature: Group navigation menu links

  Background:
    Given the following collection content:
      | title            | website url         |
      | Imperial Library | https://example.com |

  Scenario: Collection menus visibility. Glossary is handled in other scenario.
    Given I am logged in as a facilitator of the "Imperial Library" collection
    When I go to the homepage of the "Imperial Library" collection
    Then I should see the link "Overview" in the "Navigation menu"
    And I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "Page 1" in the "Navigation menu" region
    And I should not see the link "Page 2" in the "Navigation menu" region
    And I should see the link "Website link" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given the following "discussion" content:
      | title          | collection       |
      | The Big Debate | Imperial Library |
    And the following "custom_page" content:
      | title  | collection       |
      | Page 1 | Imperial Library |
      | Page 2 | Imperial Library |
    And I update "Imperial Library" collection:
      | website_url |  |

    When I reload the page
    Then I should see the link "Overview" in the "Navigation menu"
    But I should see the link "Discussions" in the "Navigation menu" region
    And I should see the link "Page 1" in the "Navigation menu" region
    And I should see the link "Page 2" in the "Navigation menu" region
    And I should not see the link "Website link" in the "Quick links" region
    And I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given I delete the "Page 2" custom_page
    When I reload the page
    Then I should see the link "Page 1" in the "Navigation menu" region
    And I should not see the link "Page 2" in the "Navigation menu" region

    Given I delete the "The Big Debate" discussion
    When I reload the page
    Then I should see the link "Overview" in the "Navigation menu"
    But I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "Website link" in the "Quick links" region
    And I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    When I update "Imperial Library" collection:
      | website_url | https://example.com/2 |
    And I reload the page
    Then I should see the link "Overview" in the "Navigation menu"
    But I should not see the link "Discussions" in the "Navigation menu" region
    And I should see the link "Website link" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Test Overview link with a customized label.
    Given I click "Settings"
    When I fill in "Overview label" with "About us"
    And I press "Save"
    Then I should not see the link "Overview" in the "Navigation menu"
    But I should see the link "About us" in the "Navigation menu"
    And I should not see the link "Discussions" in the "Navigation menu" region
    And I should see the link "Website link" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Test the "Edit menu" functionality.
    Given I click the contextual link "Add new page" in the "Header" region
    When I fill in the following:
      | Title    | Page 3   |
      | Abstract | Abstract |
    And I select "Demography" options from "Topic"
    And I press "Publish"
    Then I should see the success message "Custom page Page 3 has been created."
    And I should see the link "About us" in the "Navigation menu"
    And I should see the link "Page 3" in the "Navigation menu"
    And I should see the link "Page 1" in the "Navigation menu"

    # Edit the navigation menu
    Given I click the contextual link "Edit menu" in the "Header" region
    Then I should see the heading "Edit Navigation menu of the Imperial Library collection"
    And the navigation menu of the "Imperial Library" collection should have 3 items
    # When all pages are disabled, a message should be shown.
    When I disable "About us" in the navigation menu of the "Imperial Library" collection
    And I disable "Page 1" in the navigation menu of the "Imperial Library" collection
    And I disable "Page 3" in the navigation menu of the "Imperial Library" collection
    When I go to the homepage of the "Imperial Library" collection
    Then I should see the text "All the pages have been disabled for this collection. You can edit the menu configuration or add a new page."
    And I should see the contextual link "Edit menu" in the "Header" region
    And I should not see the link "About us" in the "Navigation menu"
    And I should not see the link "Page 3" in the "Navigation menu"
    And I should not see the link "Page 1" in the "Navigation menu"
    And I should see the link "Website link" in the "Quick links" region
    And I should not have access to the standard OG menu routes for the navigation menu of the "Imperial Library" collection

    # Edit the quick links menu
    When I click the contextual link "Edit menu" in the "Content" region
    Then I should see the heading "Edit Quick links menu of the Imperial Library collection"
    And the quick_links menu of the "Imperial Library" collection should have 3 items
    When I enable "Page 1" in the quick_links menu of the "Imperial Library" collection
    And I go to the homepage of the "Imperial Library" collection
    Then I should see the link "Page 1" in the "Quick links" region
    Then I should see the link "Website link" in the "Quick links" region
    And I should not have access to the standard OG menu routes for the quick_links menu of the "Imperial Library" collection

    # Test as a normal member of the group.
    Given I am logged in as a member of the "Imperial Library" collection
    When I go to the homepage of the "Imperial Library" collection
    Then I should not have access to the standard OG menu routes for the quick_links menu of the "Imperial Library" collection
    And I should not see the contextual link "Edit menu" in the "Header" region
    And I should not see the contextual link "Add new page" in the "Header" region
    And I should not see the contextual link "Edit menu" in the "Content" region
    And I should not see the contextual link "Add new page" in the "Content" region

    # Test as anonymous.
    Given I am logged in as a member of the "Imperial Library" collection
    When I go to the homepage of the "Imperial Library" collection
    Then I should not have access to the standard OG menu routes for the quick_links menu of the "Imperial Library" collection
    And I should not see the contextual link "Edit menu" in the "Header" region
    And I should not see the contextual link "Add new page" in the "Header" region
    And I should not see the contextual link "Edit menu" in the "Content" region
    And I should not see the contextual link "Add new page" in the "Content" region

  Scenario: Solution menus visibility. Glossary is handled in other scenario.
    Given the following solution content:
      | title              | collection       | landing page       |
      | The Alchemy Sphere | Imperial Library | http://example.com |
    And I am logged in as a facilitator of the "The Alchemy Sphere" solution

    When I go to the homepage of the "The Alchemy Sphere" solution
    Then I should see the link "Overview" in the "Navigation menu"
    And I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "EIF Perspective" in the "Navigation menu" region
    And I should not see the link "Page 1" in the "Navigation menu" region
    And I should not see the link "Page 2" in the "Navigation menu" region
    And I should not see the link "Release history" in the "Quick links" region
    And I should not see the link "EIF Perspective" in the "Quick links" region
    But I should see the link "Website link" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given the following discussion content:
      | title          | solution           |
      | The Big Debate | The Alchemy Sphere |
    And the following release content:
      | title                 | is version of      |
      | Release the innocents | The Alchemy Sphere |
    And the following EIF perspective data for the "The Alchemy Sphere" solution:
      | Description | Axonometric |

    And the following custom_page content:
      | title  | collection         |
      | Page 1 | The Alchemy Sphere |
      | Page 2 | The Alchemy Sphere |
    When I update "The Alchemy Sphere" solution:
      | landing_page |  |
    And I reload the page

    Then I should see the link "Overview" in the "Navigation menu"
    But I should see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "EIF Perspective" in the "Navigation menu" region
    And I should see the link "Page 1" in the "Navigation menu" region
    And I should see the link "Page 2" in the "Navigation menu" region
    And I should see the link "Release history" in the "Quick links" region
    And I should see the link "EIF Perspective" in the "Quick links" region
    But I should not see the link "Website link" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given I delete the "Page 2" custom_page
    When I reload the page
    Then I should see the link "Page 1" in the "Navigation menu" region
    And I should not see the link "Page 2" in the "Navigation menu" region

    Given I delete the "The Big Debate" discussion
    And I delete the "Release the innocents" release
    And I delete the EIF perspective of the "The Alchemy Sphere" solution
    When I reload the page
    Then I should see the link "Overview" in the "Navigation menu"
    But I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "EIF Perspective" in the "Navigation menu" region
    And I should not see the link "Release history" in the "Quick links" region
    And I should not see the link "EIF Perspective" in the "Quick links" region
    And I should not see the link "Website link" in the "Quick links" region
    But I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    When I update "The Alchemy Sphere" solution:
      | landing_page | https://example.com/2 |
    And I reload the page
    Then I should see the link "Overview" in the "Navigation menu"
    But I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "EIF Perspective" in the "Navigation menu" region
    And I should not see the link "Release history" in the "Quick links" region
    And I should not see the link "EIF Perspective" in the "Quick links" region
    But I should see the link "Website link" in the "Quick links" region
    But I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Test Overview link with a customized label.
    Given I click "Settings"
    When I fill in "Overview label" with "About us"
    And I press "Save"
    Then I should not see the link "Overview" in the "Navigation menu"
    But I should see the link "About us" in the "Navigation menu"
    And I should not see the link "Discussions" in the "Navigation menu" region
    And I should not see the link "EIF Perspective" in the "Navigation menu" region
    And I should not see the link "Release history" in the "Quick links" region
    And I should see the link "Website link" in the "Quick links" region
    But I should not see the link "EIF Perspective" in the "Quick links" region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

  Scenario: Glossary links
    Given the following collection content:
      | title  |
      | Target |
    Given the following solution content:
      | title     | collection       |
      | Solution1 | Imperial Library |
      | Solution2 | Imperial Library |
    And I am logged in as a facilitator of the "Solution1" solution

    When I go to the homepage of the "Imperial Library" collection
    Then I should not see the link "Glossary" in the "Navigation menu" region
    When I go to the homepage of the "Solution1" solution
    Then I should not see the link "Glossary" in the "Navigation menu" region
    When I go to the homepage of the "Solution2" solution
    Then I should not see the link "Glossary" in the "Navigation menu" region

    Given the following "glossary" content:
      | title   | definition  | collection       |
      | Amazing | Amazing def | Imperial Library |
      | Awful   | Awful def   | Imperial Library |
      | Neutral | Neutral def | Target           |
    When I go to the homepage of the "Imperial Library" collection
    Then I should see the link "Glossary" in the "Navigation menu" region
    And the response should contain "/collection/imperial-library/glossary"

    # Links are still not shown in solutions because they are creates disabled.
    When I go to the homepage of the "Solution1" solution
    Then I should not see the link "Glossary" in the "Navigation menu" region
    When I go to the homepage of the "Solution2" solution
    Then I should not see the link "Glossary" in the "Navigation menu" region

    # Enable the Glossary solution link and visit again Solution1.
    And I go to the homepage of the "Solution1" solution
    When I click the contextual link "Edit menu" in the "Header" region
    When I enable "Glossary" in the navigation menu of the "Solution1" solution
    And I go to the homepage of the "Solution1" solution
    Then I should see the link "Glossary" in the "Navigation menu" region
    # The solution Glossary link points to the parent collection glossary link.
    And the response should contain "/collection/imperial-library/glossary"

    # Move solution to another collection.
    Given I am logged in as a moderator
    And I go to the homepage of the "Imperial Library" collection
    When I click "Manage content"
    When I select the "Solution1" row
    And I select "Move to other group" from "Action"
    When I press "Apply to selected items"
    And I fill in "Select the destination collection" with "Target"
    When I press "Apply"
    When I go to the homepage of the "Solution1" solution
    Then I should see the link "Glossary"
    And the response should contain "/collection/target/glossary"

    # Move solution back.
    And I go to the homepage of the "Target" collection
    When I click "Manage content"
    When I select the "Solution1" row
    And I select "Move to other group" from "Action"
    When I press "Apply to selected items"
    And I fill in "Select the destination collection" with "Imperial Library"
    When I press "Apply"
    When I go to the homepage of the "Solution1" solution
    Then I should see the link "Glossary"
    And the response should contain "/collection/imperial-library/glossary"

    # Delete the Neutral glossary
    Given I delete the "Neutral" content
    When I go to the homepage of the "Imperial Library" collection
    When I click "Manage content"
    When I select the "Solution1" row
    And I select "Move to other group" from "Action"
    When I press "Apply to selected items"
    And I fill in "Select the destination collection" with "Target"
    And I press "Apply"
    When I go to the homepage of the "Target" collection
    Then I should not see the link "Glossary"
    When I go to the homepage of the "Solution1" solution
    # No Glossary link because the parent is missing the link too.
    Then I should not see the link "Glossary"
    And the response should not contain "/collection/target/glossary"
    And the response should not contain "/collection/imperial-library/glossary"

  @javascript
  Scenario: Menus with items that do not fit in available space should be wrapped in a dropdown
    Given the following "custom_page" content:
      | title              | collection       | topic |
      | Molds for candles  | Imperial Library | HR    |
      | Molds for walls    | Imperial Library | HR    |
      | Molds for crafts   | Imperial Library | HR    |
      | Molds for pottery  | Imperial Library | HR    |
      | Molds for ceramics | Imperial Library | HR    |
      | Molds for soaps    | Imperial Library | HR    |
    When I go to the "Imperial Library" collection
    Then I should see the link "Molds for pottery" in the "Navigation menu"
    But I should not see the text "See more" in the "Navigation menu"
    Given the following "custom_page" content:
      | title           | collection       | topic |
      | Vintage molds   | Imperial Library | HR    |
      | Plaster casting | Imperial Library | HR    |
    When I reload the page
    Then I should see the text "See more" in the "Navigation menu"
    But I should not see the text "Plaster casting" in the "Navigation menu"
    When I press "See more"
    Then I should see the text "Plaster casting" in the "Navigation menu"

  @javascript
  Scenario: The contextual links button in the navigation menu should be always visible
    Given the following "custom_page" content:
      | title           | collection       | topic |
      | Mists of dreams | Imperial Library | HR    |
    When I am logged in as a facilitator of the "Imperial Library" collection
    And I go to the homepage of the "Imperial Library" collection
    Then I should see the contextual links button in the "Navigation menu"
    And the "Edit menu" link in the "Navigation menu" should not be visible
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should be visible
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should not be visible

  Scenario: Synchronize titles of custom pages and menu links
    Given the following "custom_page" content:
      | title       | collection       | topic |
      | Tree eaters | Imperial Library | HR    |
    When I am logged in as a facilitator of the "Imperial Library" "collection"
    And I go to the homepage of the "Imperial Library" collection
    Then I should see the link "Tree eaters" in the "Navigation menu"
    When I click "Tree eaters"
    And I click "Edit" in the "Entity actions" region
    And I fill in "Title" with "Furry machines"
    And I press "Update"
    And I go to the homepage of the "Imperial Library" collection
    Then I should see the link "Furry machines" in the "Navigation menu"

  @javascript
  Scenario: Only custom page entries can be nested in the navigation menu.
    Given the following "custom_page" content:
      | title     | collection       | topic |
      | Types     | Imperial Library | HR    |
      | Frameless | Imperial Library | HR    |
      | Bodypack  | Imperial Library | HR    |
    And the following custom pages navigation menu structure for the "Imperial Library" collection:
      | title     | parent | weight |
      | Types     |        | 3      |
      | Frameless | Types  | 4      |
    When I am logged in as a facilitator of the "Imperial Library" collection
    And I go to the "Imperial Library" collection
    And I click the contextual link "Edit menu" in the "Header" region
    When I drag the "Frameless" table row to the left
    Then the draggable navigation menu table of the "Imperial Library" collection should be:
      | title     | parent |
      | Overview  |        |
      | Bodypack  |        |
      | Types     |        |
      | Frameless |        |
    # Links that don't refer to a node cannot be nested.
    When I drag the "Overview" table row to the right
    Then the draggable navigation menu table of the "Imperial Library" collection should be:
      | title     | parent |
      | Overview  |        |
      | Bodypack  |        |
      | Types     |        |
      | Frameless |        |

  Scenario: Facilitator sees and uses edit links for custom pages in group menu
    Given the following "custom_page" content:
      | title      | collection       |
      | Typewriter | Imperial Library |
    When I am logged in as a facilitator of the "Imperial Library" collection
    And I go to the "Imperial Library" collection
    And I click the contextual link "Edit menu" in the "Header" region
    Then the "group menu edit table" table should contain the following columns:
      | Page       | Operations  |
      | Overview   | Edit        |
      | Typewriter | Edit Delete |
    When I click "Edit" in the "Typewriter" row
    Then I should see the heading "Edit Custom page Typewriter"

  @javascript
  Scenario: Test quick links menu structure.
    Given the following "custom_page" content:
      | title         | collection       |
      | Custom page 1 | Imperial Library |
      | Custom page 2 | Imperial Library |
      | Custom page 3 | Imperial Library |
      | Custom page 4 | Imperial Library |
      | Custom page 5 | Imperial Library |
    When I am logged in as a facilitator of the "Imperial Library" collection
    And I go to the "Imperial Library" collection
    And I click the contextual link "Edit menu" in the "Quick links" region
    Then the draggable quick_links menu table of the "Imperial Library" collection should be:
      | title         |
      | Custom page 1 |
      | Custom page 2 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |
      | Website link  |

    When I drag the "Custom page 2" table row up
    Then the draggable quick_links menu table of the "Imperial Library" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |
      | Website link  |

    And I press "Save"
    Then I should see the text "Quick links"
    And I click the contextual link "Edit menu" in the "Quick links" region
    Then the draggable quick_links menu table of the "Imperial Library" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |
      | Website link  |

    # Nesting functionality should be disabled.
    When I drag the "Custom page 2" table row left
    When I drag the "Custom page 1" table row right
    Then the draggable quick_links menu table of the "Imperial Library" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |
      | Website link  |
