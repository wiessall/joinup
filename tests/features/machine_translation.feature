@api
Feature: Machine translation
  As a citizen of the European Union
  I am likely not to have English as my native language
  So it would be helpful to have a button that can translate the page content

  @javascript
  Scenario: Translation link is only visible on content focused pages
    Given collection content:
      | title  | state     |
      | Brexit | published |
    And event content:
      | title                      | collection | state     |
      | Confiscating of sandwiches | Brexit     | published |
    And news content:
      | title                   | collection | state     |
      | Supply chain disruption | Brexit     | published |
    And document content:
      | title                           | collection | state     |
      | Increased postal delivery costs | Brexit     | published |
    And discussion content:
      | title                                    | collection | state     |
      | Restriction on animal-based food imports | Brexit     | published |
    And custom_page content:
      | title                      | collection | state     |
      | Increased credit card fees | Brexit     | published |
    And glossary content:
      | title               | abbreviation | summary                 | definition                | collection |
      | Digital portability | DP           | Availability of content | Across streaming services | Brexit     |
    And solution content:
      | title         | state     | collection |
      | Rejoin the EU | published | Brexit     |

    When I am logged in as a moderator
    # Inside a collection sidebar, only custom pages, glossary terms (but not
    # the glossary overview) should be translatable.
    When I go to the edit form of the "Brexit" collection
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Brexit" collection
    Then I should not see any Webtools eTrans elements
    When I go to the "Brexit" collection
    Then I should see the Webtools eTrans button
    When I am on the members page of "Brexit"
    Then I should not see any Webtools eTrans elements
    When I click "Glossary"
    Then I should see the Webtools eTrans button
    When I click "Digital portability"
    Then I should see the Webtools eTrans button
    When I go to the "Increased credit card fees" custom page
    Then I should see the Webtools eTrans button
    When I go to the edit form of the "Increased credit card fees" "custom page"
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Increased credit card fees" "custom page"
    Then I should not see any Webtools eTrans elements

    When I go to the edit form of the "Rejoin the EU" solution
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Rejoin the EU" solution
    Then I should not see any Webtools eTrans elements
    When I go to the "Rejoin the EU" solution
    Then I should see the Webtools eTrans button
    When I am on the members page of "Rejoin the EU"
    Then I should not see any Webtools eTrans elements

    # Community content should be translatable.
    When I go to the "Confiscating of sandwiches" event
    Then I should see the Webtools eTrans button
    When I go to the edit form of the "Confiscating of sandwiches" event
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Confiscating of sandwiches" event
    Then I should not see any Webtools eTrans elements
    When I go to the "Supply chain disruption" news
    Then I should see the Webtools eTrans button
    When I go to the edit form of the "Supply chain disruption" news
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Supply chain disruption" news
    Then I should not see any Webtools eTrans elements
    When I go to the "Increased postal delivery costs" document
    Then I should see the Webtools eTrans button
    When I go to the edit form of the "Increased postal delivery costs" document
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Increased postal delivery costs" document
    Then I should not see any Webtools eTrans elements
    When I go to the "Restriction on animal-based food imports" discussion
    Then I should see the Webtools eTrans button
    When I go to the edit form of the "Restriction on animal-based food imports" discussion
    Then I should not see any Webtools eTrans elements
    When I go to the delete form of the "Restriction on animal-based food imports" discussion
    Then I should not see any Webtools eTrans elements

    # Pages that primarily list content should not be translatable.
    When I go to the homepage
    Then I should not see any Webtools eTrans elements
    When I visit the search page
    Then I should not see any Webtools eTrans elements
    When I visit the collection overview
    Then I should not see any Webtools eTrans elements
    When I visit the solution overview
    Then I should not see any Webtools eTrans elements
    And I visit the content overview
    Then I should not see any Webtools eTrans elements

    # Pages that are not content focused should not be translatable.
    When I click "Contact Portal Support" in the "Footer" region
    Then I should not see any Webtools eTrans elements
