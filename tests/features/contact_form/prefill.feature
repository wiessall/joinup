@api @group-c
Feature: Prefill contact form fields
  In order to simplify the gathering of feedback from the users
  As a moderator
  I want to be able to prefill some fields of the contact form

  Scenario: The subject field can be prefilled through links.
    Given collection content:
      | title                | state     | topic |
      | CAMSS test community | published | HR    |
    And custom_page content:
      | title                | collection           | state     |
      | IOP page custom page | CAMSS test community | published |
    And I append "simple_paragraph" to "field_paragraphs_body" field in "node" entity with following "IOP page custom page" title:
      | field_body:value  | <p>Follow this <a href="../../contact?subject=CAMSS%20Change%20Request">link</a></p> |
      | field_body:format | content_editor                                                                       |
    When I go to the "IOP page custom page" custom page
    And I click "link"
    Then I should see the heading "Contact"
    And the "Subject" field should contain "CAMSS Change Request"
