@api @group-d
Feature: Permalinks for custom page attachments.

  Scenario: Users can download attachments using the permalinks.
    Given the following "collection" content:
      | title            |
      | Predictable name |
    And the following "custom_page" content:
      | title           | body        | collection       | attachments                                                          |
      | Predictable CP1 | Lorem ipsum | Predictable name | empty.rdf, empty_pdf.pdf, invalid_adms.rdf, test.zip, text.pdf       |
      | Predictable CP2 | Lorem ipsum | Predictable name | ada.png, alan.jpg, blaise.jpg, charles.jpg, leonardo.jpg, linus.jpeg |
    And the following "news" content:
      | title           | headline        | collection       | attachments |
      | Predictable NC1 | Predictable NC1 | Predictable name | empty.rdf   |

    When I go to "/collection/predictable-name/predictable-cp1"
    Then I should see the text "Predictable CP1"
    When I go to "/collection/predictable-name/predictable-cp1/f/0"
    # The first file is the delta 0 but in the URL, it is 1.
    Then I should see the heading "Page not found"
    When I go to "/collection/predictable-name/predictable-cp1/f/1"
    # The content disposition is the header that carries information about the
    # file downloaded. The "(_\d+)?\." part is to match the file name with the
    # possible suffix - if the same file is created in an earlier test.
    Then the content type of the response should be "application/rdf+xml"
    When I go to "/collection/predictable-name/predictable-cp1/f/2"
    Then the content type of the response should be "application/pdf"
    When I go to "/collection/predictable-name/predictable-sp1/f/5"
    Then I should see the heading "Page not found"
    When I go to "/collection/predictable-name/predictable-sp1/f/some-string"
    Then I should see the heading "Page not found"
    When I go to "/collection/predictable-name/predictable-sp1/f/-1"
    Then I should see the heading "Page not found"

    When I go to "/collection/predictable-name/predictable-cp2/f/1"
    Then the content type of the response should be "image/png"
    When I go to "/collection/predictable-name/predictable-cp2/f/6"
    Then the content type of the response should be "image/jpeg"
    When I go to "/collection/predictable-name/predictable-cp2/f/7"
    Then I should see the heading "Page not found"

    # Ensure the file downlaod functionality only works for custom pages. Other
    # types simply throw a page not found. The validation is on the route level
    # so we only need to check that a page not found is returned and there is
    # no exception thrown.
    When I go to "/collection/predictable-name/predictable-nc1/f/1"
    Then I should see the heading "Page not found"
