@api @group-g
Feature: Prefill contact form fields
  In order to simplify the gathering of feedback from the users
  As a moderator
  I want to be able to prefill some fields of the contact form

  Scenario: The subject field can be prefilled through links.
    Given collection:
      | title | CAMSS test community |
      | state | validated            |
    And custom_page content:
      | title    | collection           | state     | field_paragraphs_body                                                                |
      | IOP page | Interoperable Europe | validated | <p>Follow this <a href="../../contact?subject=CAMSS%20Change%20Request">link</a></p> |

    When I go to the "IOP page" custom page
    And I click "link"
    Then I should see the heading "Contact"
    And the "Subject" field should contain "CAMSS Change Request"
