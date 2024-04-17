@api @group-b
Feature: Navigation menu for custom pages
  In order to determine the order and visibility of custom pages in the navigation menu
  As a facilitator
  I need to be able to manage the navigation menu

  Scenario Outline: Access the navigation menu through the contextual link
    Given the following <group>s:
      | title            | logo     | state     |
      | Rainbow tables   | logo.png | published |
      | Cripple Mr Onion | logo.png | published |
    When I am logged in as a facilitator of the "Rainbow tables" <group>
    And I go to the homepage of the "Rainbow tables" <group>
    Then the navigation menu of the "Rainbow tables" <group> should have <visible items 1> visible items
    And I should see the following navigation menu items in the specified order:
      | text     |
      | Overview |
    # Check that the 'Edit menu' local action is present.
    And I should see the contextual link "Edit menu" in the "Header" region
    # The 'Add link' local action that is present in the default implementation
    # of OG Menu should not be visible. We are managing the menu links behind
    # the scenes. The end user should not be able to interact with these.
    But I should not see the contextual link "Add link" in the "Header" region

    # When we create a custom page it should automatically show up in the menu.
    When I click the contextual link "Add new page" in the "Header" region
    Then I should see the heading "Add custom page"
    When I fill in the following:
      | Title  | About us    |
    And I select "HR" from "Topic"
    And I press "Publish"
    Then I should see the success message "Custom page About us has been created."
    And the navigation menu of the "Rainbow tables" <group> should have <visible items 2> visible items

    When I click the contextual link "Edit menu" in the "Header" region
    Then the navigation menu of the "Rainbow tables" <group> should have 4 items

    # When all the pages are disabled in the navigation menu, a message should
    # be shown to the user.
    When I disable "Overview" in the navigation menu of the "Rainbow tables" <group>
    And I disable "About us" in the navigation menu of the "Rainbow tables" <group>
    And I go to the homepage of the "Rainbow tables" <group>
    Then I should see the text "All the pages have been disabled for this <group>. You can edit the menu configuration or add a new page."
    And I should see the contextual link "Edit menu" in the "Header" region

    # The contextual menu can be used to navigate to the menu edit page.
    When I click the contextual link "Edit menu" in the "Header" region
    Then I should see the heading "Edit navigation menu of the Rainbow tables <group>"

    # The form to add a new menu link should not be accessible by anyone. This
    # is functionality provided by Drupal which is intended for webmasters. We
    # are showing the menu overview to collection facilitators so they can
    # reorder the navigation menu, but they should not be able to access the
    # related menu administration screens.
    And I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>

    # Create a few custom pages in the second collection so we can check if the
    # right menu shows up in each collection.
    Given custom_page content:
      | title           | body                                                                                                                                  | <group>          | state     | topic |
      | Eights are wild | You cannot Cripple Mr Onion if your running flush contains more wild eights than the Lesser or Great Onion you are trying to cripple. | Cripple Mr Onion | published | HR    |
      | Eights are null | They can be included in an existing Onion in order to improve its size by one card.                                                   | Cripple Mr Onion | published | HR    |

    # Test as a normal member of the group.
    Given I am logged in as a member of the "Rainbow tables" <group>
    When I go to the homepage of the "Rainbow tables" <group>
    # Members of the collection should not have access to the administration
    # pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>
    # The navigation link from the current group should be visible, but not
    # the link from the second collection.
    But I should not see the link "Eights are wild" in the "Navigation menu"
    And I should not see the link "Eights are null" in the "Navigation menu"
    # Test the navigation link of the second collection.
    When I go to the homepage of the "Cripple Mr Onion" <group>
    Then I should see the link "Eights are wild" in the "Navigation menu"
    And I should see the link "Eights are null" in the "Navigation menu"
    But I should not see the link "About us" in the "Navigation menu"

    # Test as a moderator.
    Given I am logged in as a moderator
    When I go to the homepage of the "Rainbow tables" <group>
    # Even moderators should not have access to the administration pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>
    # The navigation link from the current group should be visible, but not
    # the link from the second group.
    But I should not see the link "Eights are wild" in the "Navigation menu"
    And I should not see the link "Eights are null" in the "Navigation menu"
    # Test the navigation link of the second collection.
    When I go to the homepage of the "Cripple Mr Onion" <group>
    Then I should see the link "Eights are wild" in the "Navigation menu"
    And I should see the link "Eights are null" in the "Navigation menu"
    But I should not see the link "About us" in the "Navigation menu"

    # Test as an anonymous user.
    Given I am an anonymous user
    When I go to the homepage of the "Rainbow tables" <group>
    # Anonymous users should definitely not have access to the administration
    # pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>

    # The navigation link from the current collection should be visible, but not
    # the link from the second group.
    But I should not see the link "Eights are wild" in the "Navigation menu"
    And I should not see the link "Eights are null" in the "Navigation menu"
    # Test the navigation link of the second group.
    When I go to the homepage of the "Cripple Mr Onion" <group>
    Then I should see the link "Eights are wild" in the "Navigation menu"
    And I should see the link "Eights are null" in the "Navigation menu"
    But I should not see the link "About us" in the "Navigation menu"

    Examples:
      | group      | visible items 1 | visible items 2 |
      | collection | 3               | 4               |
      | solution   | 2               | 3               |

  @javascript
  Scenario: Menus with items that do not fit in available space should be wrapped in a dropdown
    Given collection:
      | title | Plaster molds |
      | state | published     |
    And custom_page content:
      | title              | body                   | collection    | state     | topic |
      | Molds for candles  | This is a sample body. | Plaster molds | published | HR    |
      | Molds for walls    | This is a sample body. | Plaster molds | published | HR    |
      | Molds for crafts   | This is a sample body. | Plaster molds | published | HR    |
      | Molds for pottery  | This is a sample body. | Plaster molds | published | HR    |
      | Molds for ceramics | This is a sample body. | Plaster molds | published | HR    |
      | Molds for soaps    | This is a sample body. | Plaster molds | published | HR    |
    # Menu items are displayed normally
    When I go to the "Plaster molds" collection
    Then I should see the link "Molds for pottery" in the "Navigation menu"
    But I should not see the text "See more" in the "Navigation menu"
    # A menu with items that do not fit the available space are in a dropdown
    Given custom_page content:
      | title              | body                   | collection    | state     | topic |
      | Vintage molds      | This is a sample body. | Plaster molds | published | HR    |
      | Plaster casting    | This is a sample body. | Plaster molds | published | HR    |
    When I go to the "Plaster molds" collection
    Then I should see the text "See more" in the "Navigation menu"
    But I should see the text "Plaster casting" in the "Navigation menu"
    And I should not see the text "Vintage molds" in the "Navigation menu"
    Then I press "See more"
    And I should see the text "Vintage molds" in the "Navigation menu"

  @javascript
  Scenario Outline: The contextual links button in the navigation menu should be always visible
    # In order to easily discover that I can manage the items in the navigation menu
    # As a group facilitator
    # I should see a button in the navigation menu that displays options when clicked
    Given the following <group>:
      | title | Prism Gazers |
      | logo  | logo.png     |
      | state | published    |
    And custom_page content:
      | title           | body                   | <group>      | state     | topic |
      | Mists of dreams | This is a sample body. | Prism Gazers | published | HR    |
    When I am logged in as a facilitator of the "Prism Gazers" <group>
    And I go to the homepage of the "Prism Gazers" <group>
    Then I should see the contextual links button in the "Navigation menu"
    # The links to manage the navigation menu should only appear after clicking on the button.
    And the "Edit menu" link in the "Navigation menu" should not be visible
    And the "Add new page" link in the "Navigation menu" should not be visible
    # Click the button, now the links appear.
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should be visible
    And the "Add new page" link in the "Navigation menu" should be visible
    # Click the button a second time to hide the links again.
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should not be visible
    And the "Add new page" link in the "Navigation menu" should not be visible

    Examples:
      | group      |
      | collection |
      | solution   |

  Scenario Outline: Synchronize titles of custom pages and menu links
    Given the following <group>:
      | title | Ravenous wood-munching alphabeavers |
      | state | published                           |
    And custom_page content:
      | title       | body                                                                | <group>                             | topic | state     |
      | Tree eaters | Given time, they will most likely strip the entire region of trees. | Ravenous wood-munching alphabeavers | HR    | published |
    When I am logged in as a facilitator of the "Ravenous wood-munching alphabeavers" "<group>"
    And I go to the homepage of the "Ravenous wood-munching alphabeavers" <group>
    Then I should see the link "Tree eaters" in the "Navigation menu"

    # Change the title and check that the link is updated.
    When I click "Tree eaters"
    And I click "Edit" in the "Entity actions" region
    And I fill in "Title" with "An army of furry little killing machines"
    And I press "Update"
    And I go to the homepage of the "Ravenous wood-munching alphabeavers" <group>
    Then I should see the link "An army of furry little killing machines" in the "Navigation menu"
    And I should not see the link "Tree eaters" in the "Navigation menu"

    Examples:
      | group      |
      | collection |
      | solution   |

  Scenario Outline: Only the links below the topmost page are rendered in TOC.
    Given the following <group>s:
      | title                   | state     |
      | Table of contents group | published |
    And custom_page content:
      | title         | <group>                 | state     | topic |
      | Page 1        | Table of contents group | published | HR    |
      | Page 2        | Table of contents group | published | HR    |
      | Page 3        | Table of contents group | published | HR    |
      | Subpage 1-1   | Table of contents group | published | HR    |
      | Subpage 1-1-1 | Table of contents group | published | HR    |
      | Subpage 1-2   | Table of contents group | published | HR    |
      | Subpage 1-2-1 | Table of contents group | published | HR    |
      | Subpage 1-2-2 | Table of contents group | published | HR    |
      | Subpage 2-1   | Table of contents group | published | HR    |
    And the following custom pages navigation menu structure for the "Table of contents group" <group>:
      | title         | parent      | weight |
      | Page 1        |             | 1      |
      | Page 2        |             | 2      |
      | Page 3        |             | 3      |
      | Subpage 1-1   | Page 1      | 1      |
      | Subpage 1-1-1 | Subpage 1-1 | 1      |
      | Subpage 1-2   | Page 1      | 2      |
      | Subpage 1-2-1 | Subpage 1-2 | 1      |
      | Subpage 1-2-2 | Subpage 1-2 | 2      |
      | Subpage 2-1   | Page 2      | 1      |

    When I am logged in as a member of the "Table of contents group" <group>
    And I go to the homepage of the "Table of contents group" <group>
    Then I should not see the "Table of contents" region

    # Only the sub-page links of "Page 1" are rendered.
    When I visit the "Page 1" custom page
    Then I should see the link "Subpage 1-1" in the "Table of contents"
    And I should see the link "Subpage 1-2" in the "Table of contents"
    But I should not see the link "Page 1" in the "Table of contents"
    And I should not see the link "Page 2" in the "Table of contents"
    And I should not see the link "Subpage 2-1" in the "Table of contents"
    And I should not see the link "Page 3" in the "Table of contents"
    And I should see the link "Subpage 1-1-1" in the "Table of contents"
    And I should see the link "Subpage 1-2-1" in the "Table of contents"
    And I should see the link "Subpage 1-2-2" in the "Table of contents"

    # Visit "Subpage 1-1" to verify that appropriate children are expanded.
    When I visit the "Subpage 1-1" custom page
    Then I should see the link "Subpage 1-1" in the "Table of contents"
    And I should see the link "Subpage 1-1-1" in the "Table of contents"
    And I should see the link "Subpage 1-2" in the "Table of contents"
    But I should not see the link "Page 1" in the "Table of contents"
    And I should not see the link "Page 2" in the "Table of contents"
    And I should not see the link "Subpage 2-1" in the "Table of contents"
    And I should not see the link "Page 3" in the "Table of contents"
    And I should see the link "Subpage 1-2-1" in the "Table of contents"
    And I should see the link "Subpage 1-2-2" in the "Table of contents"

    # Visit "Subpage 1-2" to verify that appropriate children are expanded.
    When I visit the "Subpage 1-2" custom page
    Then I should see the link "Subpage 1-1" in the "Table of contents"
    And I should see the link "Subpage 1-2" in the "Table of contents"
    And I should see the link "Subpage 1-2-1" in the "Table of contents"
    And I should see the link "Subpage 1-2-2" in the "Table of contents"
    But I should not see the link "Page 1" in the "Table of contents"
    And I should not see the link "Page 2" in the "Table of contents"
    And I should not see the link "Subpage 2-1" in the "Table of contents"
    And I should not see the link "Page 3" in the "Table of contents"
    And I should see the link "Subpage 1-1-1" in the "Table of contents"

    # Ensure that the default links are not shown.
    And I should not see the link "Overview" in the "Table of contents"
    And I should not see the link "Members" in the "Table of contents"
    And I should not see the link "About" in the "Table of contents"
    But I should see the link "Overview" in the "Navigation menu"
    And I should not see the link "Members" in the "Navigation menu"

    # Only the sub-page links of "Page 2" are rendered.
    When I visit the "Page 2" custom page
    Then I should see the link "Subpage 2-1" in the "Table of contents"
    But I should not see the link "Page 2" in the "Table of contents"
    And I should not see the link "Page 1" in the "Table of contents"
    And I should not see the link "Subpage 1-1" in the "Table of contents"
    And I should not see the link "Subpage 1-2" in the "Table of contents"

    # On a custom page without sub-pages there's no table of contents.
    When I visit the "Page 3" custom page
    Then I should not see the "Table of contents" region

    # Verify that the menu only shows on the canonical route.
    When I am logged in as a moderator
    And I visit the "Page 1" custom page
    And I click "Edit" in the "Entity actions" region
    Then I should not see the "Table of contents" region

    Examples:
      | group      |
      | collection |
      | solution   |

  @javascript
  Scenario Outline: Only custom page entries can be nested in the navigation menu.
    Given the following <group>:
      | title | Ergonomic backpacks |
      | state | published           |
    And custom_page content:
      | title              | <group>             | state     | topic |
      | Types of backpacks | Ergonomic backpacks | published | HR    |
      | Frameless          | Ergonomic backpacks | published | HR    |
      | External frame     | Ergonomic backpacks | published | HR    |
      | Internal frame     | Ergonomic backpacks | published | HR    |
      | Bodypack           | Ergonomic backpacks | published | HR    |
    And the following custom pages navigation menu structure for the "Ergonomic backpacks" <group>:
      | title              | parent             | weight |
      | Types of backpacks |                    | 3      |
      | Frameless          | Types of backpacks | 4      |
      | External frame     | Types of backpacks | 5      |
      | Internal frame     |                    | 6      |
    When I am logged in as a facilitator of the "Ergonomic backpacks" <group>
    And I go to the "Ergonomic backpacks" <group>
    And I click the contextual link "Edit menu" in the "Header" region
    # The "About" page has been moved back to first level.
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Discussions        |                    |
      | Glossary           |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     | Types of backpacks |
      | Internal frame     |                    |
    When I drag the "External frame" table row to the left
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Discussions        |                    |
      | Glossary           |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     |                    |
      | Internal frame     |                    |
    When I drag the "Internal frame" table row to the right
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Discussions        |                    |
      | Glossary           |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     |                    |
      | Internal frame     | External frame     |
    # Links that don't refer to a node cannot be nested.
    And I drag the "Glossary" table row to the right
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Discussions        |                    |
      | Glossary           |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     |                    |
      | Internal frame     | External frame     |
    # Nor cannot have child rows.
    When I drag the "Bodypack" table row to the right
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Discussions        |                    |
      | Glossary           |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     |                    |
      | Internal frame     | External frame     |
    # But they can still be re-ordered up and down.
    When I drag the "Glossary" table row up
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Glossary           |                    |
      | Discussions        |                    |
      | Bodypack           |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | External frame     |                    |
      | Internal frame     | External frame     |
    # Links pointing to nodes can be moved too.
    When I drag the "Bodypack" table row down
    And I drag the "Bodypack" table row down
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Glossary           |                    |
      | Discussions        |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | Bodypack           | Types of backpacks |
      | External frame     |                    |
      | Internal frame     | External frame     |
    # Maximum indentation level is two.
    When I drag the "Bodypack" table row to the right
    And I drag the "Internal frame" table row to the left
    And I drag the "Internal frame" table row up
    And I drag the "Internal frame" table row to the right
    And I drag the "Internal frame" table row to the right
    Then the draggable menu table of the "Ergonomic backpacks" <group> should be:
      | title              | parent             |
      | Overview           |                    |
      | Glossary           |                    |
      | Discussions        |                    |
      | Types of backpacks |                    |
      | Frameless          | Types of backpacks |
      | Bodypack           | Frameless          |
      | Internal frame     | Frameless          |
      | External frame     |                    |

    Examples:
      | group      |
      | collection |
      | solution   |

  Scenario Outline: Show appropriate menu entries in the table of contents outline.
    Given the following <group>s:
      | title                     | state     |
      | Table of contents outline | published |
    And custom_page content:
      | title      | <group>                   | state       | topic |
      | TOCO 1     | Table of contents outline | published   | HR    |
      | TOCO 2     | Table of contents outline | published   | HR    |
      | TOCO 1-1   | Table of contents outline | published   | HR    |
      | TOCO 1-1-1 | Table of contents outline | published   | HR    |
      | TOCO 2-1   | Table of contents outline | published   | HR    |
      | TOCO 1-1-2 | Table of contents outline | published   | HR    |
      | TOCO 1-2   | Table of contents outline | published   | HR    |
      | TOCO 2-1-2 | Table of contents outline | unpublished | HR    |
      | TOCO 2-1-1 | Table of contents outline | published   | HR    |
    And the following custom pages navigation menu structure for the "Table of contents outline" <group>:
      | title      | parent   | weight |
      | TOCO 1-2   | TOCO 1   | 2      |
      | TOCO 1-1-2 | TOCO 1-1 | 2      |
      | TOCO 2-1   | TOCO 2   | 1      |
      | TOCO 1     |          | 1      |
      | TOCO 1-1-1 | TOCO 1-1 | 1      |
      | TOCO 2     |          | 2      |
      | TOCO 2-1-2 | TOCO 2-1 | 1      |
      | TOCO 1-1   | TOCO 1   | 1      |
      | TOCO 2-1-1 | TOCO 2-1 | 1      |

    When I am logged in as a member of the "Table of contents outline" <group>
    And I go to the homepage of the "Table of contents outline" <group>
    Then I should not see the "Table of contents outline" region

    When I visit the "TOCO 1" custom page
    Then I should not see the link "Up" in the "Table of contents outline"
    # This link does not exist because the outline does not contain connection to the default links just as TOC does.
    And I should not see the link "About" in the "Table of contents outline"

    # Navigate through the outline. Most links are asserted by clicking on them.
    When I click "TOCO 1-1" in the "Table of contents outline"
    Then I should see the link "TOCO 1" in the "Table of contents outline"
    And I should see the link "TOCO 1-1-1" in the "Table of contents outline"
    And I should see the link "Up" in the "Table of contents outline"
    And I click "TOCO 1-1-1" in the "Table of contents outline"
    And I click "TOCO 1-1-2" in the "Table of contents outline"
    And I click "TOCO 1-2" in the "Table of contents outline"
    And I should not see the link "TOCO 2" in the "Table of contents outline"

    When I visit the "TOCO 2" custom page
    Then I click "TOCO 2-1" in the "Table of contents outline"

    And I click "TOCO 2-1-1" in the "Table of contents outline"
    Then I should not see the link "TOCO 2-1-2" in the "Table of contents outline"
    # Navigate backwards.
    And I click "TOCO 2-1" in the "Table of contents outline"
    And I click "TOCO 2" in the "Table of contents outline"
    And I should not see the link "TOCO 1-2" in the "Table of contents outline"

    When I visit the "TOCO 1-2" custom page
    And I click "TOCO 1-1-2" in the "Table of contents outline"
    And I click "TOCO 1-1-1" in the "Table of contents outline"
    And I click "TOCO 1-1" in the "Table of contents outline"
    And I click "TOCO 1" in the "Table of contents outline"
    # Navigate through the "Up" link.
    When I visit the "TOCO 2" custom page
    Then I should not see the link "Up" in the "Table of contents outline"
    When I visit the "TOCO 1-2" custom page
    And I click "Up" in the "Table of contents outline"
    Then I should see the heading "TOCO 1"
    When I visit the "TOCO 1-1-2" custom page
    And I click "Up" in the "Table of contents outline"
    Then I should see the heading "TOCO 1-1"

    # Verify that the menu only shows on the canonical route.
    When I am logged in as a moderator
    And I visit the "TOCO 1" custom page
    And I click "Edit" in the "Entity actions" region
    Then I should not see the "Table of contents outline" region

    When I disable "TOCO 1-1-2" in the navigation menu of the "Table of contents outline" <group>
    And I visit the "TOCO 1-1-1" custom page
    Then I should not see the link "TOCO 1-1-2" in the "Table of contents outline"

    Examples:
      | group      |
      | collection |
      | solution   |

  @javascript
  Scenario Outline: Assert cache invalidation of the TOC outline.
    Given the following <group>s:
      | title                            | state     |
      | Table of contents outline cached | published |
    And custom_page content:
      | title           | <group>                          | state     | topic |
      | TOCO cached 1   | Table of contents outline cached | published | HR    |
      | TOCO cached 1-1 | Table of contents outline cached | published | HR    |
    And the following custom pages navigation menu structure for the "Table of contents outline cached" <group>:
      | title           | parent        | weight |
      | TOCO cached 1   |               | 1      |
      | TOCO cached 1-1 | TOCO cached 1 | 1      |
    When I visit the "TOCO cached 1-1" custom page
    Then I should see the link "Up" in the "Table of contents outline"

    When I am logged in as a facilitator of the "Table of contents outline cached" <group>
    And I go to the "Table of contents outline cached" <group>
    And I click the contextual link "Edit menu" in the "Header" region
    When I drag the "TOCO cached 1-1" table row to the left
    Then the draggable menu table of the "Table of contents outline cached" <group> should be:
      | title           | parent |
      | Overview        |        |
      | Discussions     |        |
      | Glossary        |        |
      | TOCO cached 1   |        |
      | TOCO cached 1-1 |        |
    When I press "Save"
    And I visit the "TOCO cached 1-1" custom page
    Then I should not see the link "Up" in the "Table of contents outline"

    Examples:
      | group      |
      | collection |
      | solution   |
