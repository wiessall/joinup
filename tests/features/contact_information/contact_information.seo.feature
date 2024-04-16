@api @group-clone
Feature: Contant Information entities don't cause exceptions because of their invalid schema

  Scenario: Check the page of an entity which had schema issues before
    Given I am logged in as a user with the "moderator" role
    When I go to the "TES team" contact information page
    Then I should get a 200 HTTP response
    And I should see the text "TES team" in the "Page title" region
    # "ContactInformation" is just a property value alias. We need to check the
    # real value.
    And 1 metatag graph of type "http://www.w3.org/2006/vcard/ns#Kind" should exist in the page
    And the metatag graph of the item with "@type" "http://www.w3.org/2006/vcard/ns#Kind" should have the following "http://www.w3.org/2006/vcard/ns#fn" properties:
      | property  | value      |
      | @value    | TES team |
    # Editing also thrown an error. Verify that it works too.
    Then I click "Edit"
    Then I should get a 200 HTTP response
    And the HTML title of the page should be "TES team"
    And I should see the text "Edit Contact information TES team"

  @javascript
  Scenario: Create contact info entity through IEF in a collection.
    Given collection:
      | title          | Contact Info Content Test |
      | state          | published                 |
    Given I am logged in as a user with the "moderator" role
    When I go to the edit form of the "Contact Info Content Test" collection
    Then I should see the text "Edit Collection Contact Info Content Test"
    And the HTML title of the page should be "Edit Rdf"

    Then I fill in "Short ID" with "contact-info-test"
    And I enter "Some description" in the "Description" wysiwyg editor
    And I select "Collaboration" from "Topic"
    And I additionally select "Social" from "Topic"

    # Owner is required.
    And I press "Add new owner"
    And I fill in "Name" with "Ms. Jane Doe PHD"
    And I check "Non-Profit Organisation"
    And I press "Create owner"

    # Add the contact information entity.
    And I fill in "Name" with "Mr. John Doe DLA"
    And I fill in "E-mail address" with "john.doe@example.com"
    And I fill in "Website URL" with "http://example.com/john-doe"
    Then I press "Create contact information"

    And I press "Publish"
    Then I should see the success message containing "Status message You've added a collection short ID: contact-info-test. It will take some time until the collection content URLs will be updated."
    And I should see the success message "A new confirmation message has been sent to john.doe@example.com."

    # Visit the canonical page of the contact information entity created by IEF.
    When I go to the "Mr. John Doe DLA" contact information page
    # "ContactInformation" is just a property value alias. We need to check the
    # real value.
    Then 1 metatag graph of type "http://www.w3.org/2006/vcard/ns#Kind" should exist in the page
    And the metatag graph of the item with "@type" "http://www.w3.org/2006/vcard/ns#Kind" should have the following "http://www.w3.org/2006/vcard/ns#fn" properties:
      | property  | value            |
      | @value    | Mr. John Doe DLA |
    Then I open the header local tasks menu
    And I click "Edit"
    Then I should see the text "Edit Contact information Mr. John Doe DLA"
