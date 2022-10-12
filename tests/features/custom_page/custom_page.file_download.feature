@api @group-f
Feature: Permalinks for custom page attachments.

  Scenario: Users can download attachments using the permalinks.
    Given collection:
      | title | Predictable name |
      | state | validated        |
    And custom_page content:
      | title           | body        | collection       | attachments                                                                                                                                                                                                          |
      | Predictable CP1 | Lorem ipsum | Predictable name | target_id: empty.rdf - display: 1, target_id: empty_pdf.pdf - display: 1, target_id: invalid_adms.rdf - display: 1, target_id: test.zip - display: 1, target_id: text.pdf - display: 1                               |
      | Predictable CP2 | Lorem ipsum | Predictable name | target_id: ada.png - display: 1, target_id: alan.jpg - display: 1, target_id: blaise.jpg - display: 1, target_id: charles.jpg - display: 1, target_id: leonardo.jpg - display: 1, target_id: linus.jpeg - display: 1 |
    And news content:
      | title           | headline        | collection       | state     |
      | Predictable NC1 | Predictable NC1 | Predictable name | validated |

    When I go to "/collection/predictable-name/predictable-cp1"
    Then I should see "Predictable CP1"
    When I go to "/collection/predictable-name/predictable-cp1/f/0"
    # The content disposition is the header that carries information about the
    # file downloaded. The "(_\d+)?\." part is to match the file name with the
    # possible suffix - if the same file is created in an earlier test.
    Then the response header "Content-Disposition" should match "attachment; filename=empty(_\d+)?\.rdf"
    When I go to "/collection/predictable-name/predictable-cp1/f/2"
    Then the response header "Content-Disposition" should match "attachment; filename=invalid_adms(_\d+)?\.rdf"
    When I go to "/collection/predictable-name/predictable-sp1/f/5"
    Then I should see the heading "Page not found"
    When I go to "/collection/predictable-name/predictable-sp1/f/some-string"
    Then I should see the heading "Page not found"
    When I go to "/collection/predictable-name/predictable-sp1/f/-1"
    Then I should see the heading "Page not found"

    When I go to "/collection/predictable-name/predictable-cp2/f/0"
    Then the response header "Content-Disposition" should match "attachment; filename=ada(_\d+)?\.png"
    When I go to "/collection/predictable-name/predictable-cp2/f/5"
    Then the response header "Content-Disposition" should match "attachment; filename=linus(_\d+)?\.jpeg"
    When I go to "/collection/predictable-name/predictable-cp2/f/6"
    Then I should see the heading "Page not found"

    # Ensure the file downlaod functionality only works for custom pages. Other
    # types simply throw a page not found. The validation is on the route level
    # so we only need to check that a page not found is returned and there is
    # no exception thrown.
    When I go to "/collection/predictable-name/predictable-nc1/f/0"
    Then I should see the heading "Page not found"
