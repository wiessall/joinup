@api
Feature:
  In order to see how content evolved over time
  As a content author or facilitator
  I need to be able to see revisions of content

  @group-c
  Scenario Outline: Community content revisions
    Given <group type> content:
      | title        | description                               | group_moderation |
      | Hellow world | Because I do not know how to spell Hello. | <moderation>     |
    And the following users:
      | Username          | E-mail                    | Roles     |
      | Clumsy Bounce     | clumsy.bounce@example.com |           |
      | Grumpy Author     | grumpy@auth.com           |           |
      | Jelly Facilitator | fajelly@gmail.com         |           |
      | Fuzzy Moderator   | fuzzy_mod@fuzzample.org   | moderator |
    And <group type> user memberships:
      | <group type> | user              | roles       |
      | Hellow world | Clumsy Bounce     |             |
      | Hellow world | Grumpy author     | author      |
      | Hellow world | Jelly Facilitator | facilitator |
    And <content type> content:
      | title                | body                                   | author        | <group type> |
      | Goodbi world         | Because I cannot spell Goodbye either. | Clumsy Bounce | Hellow world |
      | Careful with that ax | Someone needs to ax these questions.   | Grumpy Author | Hellow world |
    And <content type> revisions:
      | current title        | title                 |
      | Careful with that ax | Careful with that axe |
    And <content type> revisions:
      | current title | title         | body                       |
      | Goodbi world  | Goodbye world | I found the spell checker. |

    # Test that content author has access to revisions once there are multiple revisions
    Given I am logged in as "Jelly Facilitator"
    When I visit the "Goodbye world" <content type>
    Then I should see the link "Revisions" in the "Entity actions" region
    When I visit the revisions page for "Goodbye world"
    Then I should see the heading "Revisions for Goodbye world"

    Given I am logged in as "Clumsy Bounce"
    When I visit the "Goodbye world" <content type>
    Then I should see the link "Revisions" in the "Entity actions" region

    # Verify that revisions can be compared.
    When I click "Revisions" in the "Entity actions" region
    Then I should see the heading "Revisions for Goodbye world"
    When I press "Compare selected revisions"
    Then I should see the heading "Changes to Goodbye world"
    And I should see the following lines of text:
      | Goodbi world                           |
      | Goodbye world                          |
      | Because I cannot spell Goodbye either. |
      | I found the spell checker              |

    # Verify that an author can revert a revision but not delete it.
    When I visit the revisions page for "Goodbye world"
    Then I should see the link "Revert"
    But I should not see the link "Delete" in the "Content" region
    When I click "Revert"
    And I press "Revert"
    # If the parent group is moderated, the "Goodbye title" title will be shown
    # here because the new version will be in "proposed" state which is not
    # published.
    Then I should see the heading "Revisions for Goodbi world"
    And the "Goodbi world" <content type> should have 3 revisions
    When I go to the "Goodbi world" <content type>
    Then I should see the heading "Goodbi world"

    # Check that a facilitator can approve the restoring of a revision that has
    # been proposed by a non-author. This part of the scenario only affects
    # moderated groups. For non-moderated groups this results in no change.
    Given I am logged in as "Fuzzy Moderator"
    When I go to the edit form of the "Goodbi world" <content type>
    And I press "Update"
    Then I should see the heading "Goodbi world"

    # Users that have the 'Author' OG role can revert their content in moderated
    # groups without requiring facilitator approval. That means that the content
    # will be reverted to the previous revision without to force-set the state
    # to 'proposed'.
    Given I am logged in as "Grumpy Author"
    When I visit the "Careful with that axe" <content type>
    And I click "Revisions" in the "Entity actions" region
    And I click "Revert"
    And I press "Revert"
    Then I should see the heading "Revisions for Careful with that ax"
    When I go to the "Careful with that ax" <content type>
    Then I should see the heading "Careful with that ax"

    # Note that discussions are always considered to be post-moderated even if
    # they are posted in a pre-moderated group. The reasoning behind this is
    # everyone should always be free to start a discussion. See ISAICP-2265.
    Examples:
      | group type | content type | moderation |
      | collection | discussion   | yes        |
      | collection | document     | no         |
      | solution   | event        | no         |
      | solution   | news         | no         |

  @group-clone
  # Regression test for ISAICP-10122
  Scenario: Revisions dont work on the Country Knowledge page.
    Given I am logged in as a moderator
    When I visit the "Country Knowledge" custom_page
    Then I should see the link "Revisions" in the "Entity actions" region

    When I click "Revisions" in the "Entity actions" region
    Then I should see the heading "Revisions for Country Knowledge"
    When I press "Compare selected revisions"
    Then I should see the heading "Changes to Country Knowledge"
