@api @javascript
Feature: CKEditor5

  Background:
    Given the following "collection" content:
      | title                 | topic                   |
      | Shaping of disruption | Supplier exchange       |
      | The Other Collection  | Statistics and Analysis |
    Given the following "solution" content:
      | title       | collection            | topic             |
      | Angry Birds | Shaping of disruption | Supplier exchange |

  Scenario: CKE5 textarea do not respect any configurable sizes.
    Given the following "glossary" content:
      | title                | definition                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
      | Spatial construction | Global land use history confirms that empowering the environmental stewardship of Indigenous peoples and local communities will be critical to conserving biodiversity across the planet.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
      | Earlier transformers | "<h2>What is Lorem Ipsum? </h2> <p><strong>Lorem Ipsum</strong> is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum. </p> <h2>Why do we use it? </h2> <p>It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like). </p> <p><br>&nbsp; </p> <h2>Where does it come from? </h2> <p>Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of "de Finibus Bonorum et Malorum" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, "Lorem ipsum dolor sit amet..", comes from a line in section 1.10.32. </p> <p>The standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from "de Finibus Bonorum et Malorum" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham. </p> <h2>Where can I get some? </h2> <p>There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc. </p>" |

    Given I am logged in as a moderator
    When I go to the edit form of the "Spatial construction" glossary
    Then I check "Definition" wysiwyg height

    When I go to the edit form of the "Earlier transformers" glossary
    Then I check "Definition" wysiwyg height

  Scenario: Media image with CKEditor5.
    Given the following "Image" media:
      | name                                | image        | alt            | group                 | revision         |
      | The Image in Shaping of disruption  | simple.svg   | A simple image | Shaping of disruption | 2016-07-10T13:00 |
      | Other Image in The Other Collection | richard.jpg  | Long Live FOSS | The Other Collection  | 2016-07-11T13:00 |
      | Too angry for Angry Birds           | leonardo.jpg | The Genius     | Angry Birds           | 2016-07-26T13:00 |
      | Pager test 1                        | leonardo.jpg | pager test 1   | Angry Birds           | 2016-07-25T13:00 |
      | Pager test 2                        | leonardo.jpg | pager test 2   | Angry Birds           | 2016-07-24T13:00 |
      | Pager test 3                        | leonardo.jpg | pager test 3   | Angry Birds           | 2016-07-23T13:00 |
      | Pager test 4                        | leonardo.jpg | pager test 4   | Angry Birds           | 2016-07-22T13:00 |
      | Pager test 5                        | leonardo.jpg | pager test 5   | Angry Birds           | 2016-07-21T13:00 |
      | Pager test 6                        | leonardo.jpg | pager test 6   | Angry Birds           | 2016-07-20T13:00 |
      | Pager test 7                        | leonardo.jpg | pager test 7   | Angry Birds           | 2016-07-19T13:00 |
      | Pager test 8                        | leonardo.jpg | pager test 8   | Angry Birds           | 2016-07-18T13:00 |
      | Pager test 9                        | leonardo.jpg | pager test 9   | Angry Birds           | 2016-07-17T13:00 |
      | Pager test ten                      | leonardo.jpg | pager test 10  | Angry Birds           | 2016-07-16T13:00 |
      | Pager test eleven                   | leonardo.jpg | pager test 11  | Angry Birds           | 2016-07-15T13:00 |
      | Pager test twelve                   | leonardo.jpg | pager test 12  | Angry Birds           | 2016-07-14T13:00 |
      | Pager test thirteen                 | leonardo.jpg | pager test 13  | Angry Birds           | 2016-07-13T13:00 |
      | Pager test fourteen                 | leonardo.jpg | pager test 14  | Angry Birds           | 2016-07-12T13:00 |

    Given I am logged in as a moderator

    When I go to the edit form of the "Shaping of disruption" collection
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open

    Then I should see "The Image in Shaping of disruption"
    But I should not see "Other Image in The Other Collection"
    And I should not see "Too angry for Angry Birds"
    And I should not see "Pager test 1"
    And I should not see "Pager test 2"
    And I should not see "Pager test 3"
    And I should not see "Pager test 4"
    And I should not see "Pager test 5"
    And I should not see "Pager test 6"
    And I should not see "Pager test 7"
    And I should not see "Pager test 8"
    And I should not see "Pager test 9"
    And I should not see "Pager test ten"
    And I should not see "Pager test eleven"
    And I should not see "Pager test twelve"
    And I should not see "Pager test thirteen"
    And I should not see "Pager test fourteen"

    When I attach the file "alan.jpg" to "Add file"

    When I fill in "Name" with "This is Alan we love" in the "Modal content" region
    And I fill in "Alternative text" with "This is Alan portrait" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region

    Then I should see "The Image in Shaping of disruption"
    And I should see "This is Alan we love"
    But I should not see "Other Image in The Other Collection"
    And I should not see "Too angry for Angry Birds"
    And I should not see "Pager test 1"
    And I should not see "Pager test 2"
    And I should not see "Pager test 3"
    And I should not see "Pager test 4"
    And I should not see "Pager test 5"
    And I should not see "Pager test 6"
    And I should not see "Pager test 7"
    And I should not see "Pager test 8"
    And I should not see "Pager test 9"
    And I should not see "Pager test ten"
    And I should not see "Pager test eleven"
    And I should not see "Pager test twelve"
    And I should not see "Pager test thirteen"
    And I should not see "Pager test fourteen"

    When I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed

    When I press "Publish"
    Then the response should contain "media/image/alan.jpg"

    When I go to the edit form of the "The Other Collection" collection
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open

    Then I should see "Other Image in The Other Collection"
    But I should not see "The Image in Shaping of disruption"
    And I should not see "This is Alan we love"
    And I should not see "Too angry for Angry Birds"
    And I should not see "Pager test 1"
    And I should not see "Pager test 2"
    And I should not see "Pager test 3"
    And I should not see "Pager test 4"
    And I should not see "Pager test 5"
    And I should not see "Pager test 6"
    And I should not see "Pager test 7"
    And I should not see "Pager test 8"
    And I should not see "Pager test 9"
    And I should not see "Pager test ten"
    And I should not see "Pager test eleven"
    And I should not see "Pager test twelve"
    And I should not see "Pager test thirteen"
    And I should not see "Pager test fourteen"

    When I check "Other Image in The Other Collection"
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed

    When I press "Publish"
    Then the response should contain "media/image/richard.jpg"

    When I go to the edit form of the "Angry Birds" solution
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open

    And the "Ventuno media library" table should be:
      |  | Thumbnail | Name                      | Authored by | Updated                 |
      |  |           | Too angry for Angry Birds | Anonymous   | Tue, 26/07/2016 - 13:00 |
      |  |           | Pager test 1              | Anonymous   | Mon, 25/07/2016 - 13:00 |
      |  |           | Pager test 2              | Anonymous   | Sun, 24/07/2016 - 13:00 |
      |  |           | Pager test 3              | Anonymous   | Sat, 23/07/2016 - 13:00 |
      |  |           | Pager test 4              | Anonymous   | Fri, 22/07/2016 - 13:00 |
      |  |           | Pager test 5              | Anonymous   | Thu, 21/07/2016 - 13:00 |
      |  |           | Pager test 6              | Anonymous   | Wed, 20/07/2016 - 13:00 |
      |  |           | Pager test 7              | Anonymous   | Tue, 19/07/2016 - 13:00 |
      |  |           | Pager test 8              | Anonymous   | Mon, 18/07/2016 - 13:00 |
      |  |           | Pager test 9              | Anonymous   | Sun, 17/07/2016 - 13:00 |
      |  |           | Pager test ten            | Anonymous   | Sat, 16/07/2016 - 13:00 |
      |  |           | Pager test eleven         | Anonymous   | Fri, 15/07/2016 - 13:00 |
    And I should not see "Pager test twelve"
    And I should not see "Pager test thirteen"
    And I should not see "Pager test fourteen"
    But I should not see "The Image in Shaping of disruption"
    And I should not see "This is Alan we love"
    And I should not see "Other Image in The Other Collection"

    # Use the pager.
    When I click "Go to page 2"
    Then I should not see "Too angry for Angry Birds"
    And I should not see "Pager test 1"
    And I should not see "Pager test 2"
    And I should not see "Pager test 3"
    And I should not see "Pager test 4"
    And I should not see "Pager test 5"
    And I should not see "Pager test 6"
    And I should not see "Pager test 7"
    And I should not see "Pager test 8"
    And I should not see "Pager test 9"
    And I should not see "Pager test ten"
    And I should not see "Pager test eleven"
    But the "Ventuno media library" table should be:
      |  | Thumbnail | Name                | Authored by | Updated                 |
      |  |           | Pager test twelve   | Anonymous   | Thu, 14/07/2016 - 13:00 |
      |  |           | Pager test thirteen | Anonymous   | Wed, 13/07/2016 - 13:00 |
      |  |           | Pager test fourteen | Anonymous   | Tue, 12/07/2016 - 13:00 |

    When I close the modal dialog
    And the modal should be closed
    Then I press the button "Insert Media" in the "Description" wysiwyg editor
    And a modal should open
    And I select "Name (A-Z)" from "Sort by"
    And I press "Apply filters"
    Then the "Ventuno media library" table should be:
      |  | Thumbnail | Name                | Authored by | Updated                 |
      |  |           | Pager test 1        | Anonymous   | Mon, 25/07/2016 - 13:00 |
      |  |           | Pager test 2        | Anonymous   | Sun, 24/07/2016 - 13:00 |
      |  |           | Pager test 3        | Anonymous   | Sat, 23/07/2016 - 13:00 |
      |  |           | Pager test 4        | Anonymous   | Fri, 22/07/2016 - 13:00 |
      |  |           | Pager test 5        | Anonymous   | Thu, 21/07/2016 - 13:00 |
      |  |           | Pager test 6        | Anonymous   | Wed, 20/07/2016 - 13:00 |
      |  |           | Pager test 7        | Anonymous   | Tue, 19/07/2016 - 13:00 |
      |  |           | Pager test 8        | Anonymous   | Mon, 18/07/2016 - 13:00 |
      |  |           | Pager test 9        | Anonymous   | Sun, 17/07/2016 - 13:00 |
      |  |           | Pager test eleven   | Anonymous   | Fri, 15/07/2016 - 13:00 |
      |  |           | Pager test fourteen | Anonymous   | Tue, 12/07/2016 - 13:00 |
      |  |           | Pager test ten      | Anonymous   | Sat, 16/07/2016 - 13:00 |
    And I should not see "Pager test twelve"
    And I should not see "Pager test thirteen"
    And I should not see "Too angry for Angry Birds"
    But I should not see "The Image in Shaping of disruption"
    And I should not see "This is Alan we love"
    And I should not see "Other Image in The Other Collection"

    # Use the pager.
    When I click "Go to page 2"
    But I should not see "Pager test 1"
    And I should not see "Pager test 2"
    And I should not see "Pager test 3"
    And I should not see "Pager test 4"
    And I should not see "Pager test 5"
    And I should not see "Pager test 6"
    And I should not see "Pager test 7"
    And I should not see "Pager test 8"
    And I should not see "Pager test 9"
    And I should not see "Pager test eleven"
    And I should not see "Pager test fourteen"
    And I should not see "Pager test ten"
    And the "Ventuno media library" table should be:
      |  | Thumbnail | Name                      | Authored by | Updated                 |
      |  |           | Pager test thirteen       | Anonymous   | Wed, 13/07/2016 - 13:00 |
      |  |           | Pager test twelve         | Anonymous   | Thu, 14/07/2016 - 13:00 |
      |  |           | Too angry for Angry Birds | Anonymous   | Tue, 26/07/2016 - 13:00 |
    But I should not see "The Image in Shaping of disruption"
    And I should not see "This is Alan we love"
    And I should not see "Other Image in The Other Collection"

  # Regression test for ticket ISAICP-8806, ISAICP-8865.
  Scenario: Media type error with CKEditor5.
    Given I am logged in as a moderator
    When I go to the edit form of the "The Other Collection" collection
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open
    # There should be no JS error, AJAX should complete.
    And I press "Apply filters"
    And I press "Insert selected" in the "Modal buttons" region
    Then I should see the error message "No items selected."

  @casMockServer
  Scenario: Add test coverage for 'Contact us' wysiwyg button.
    Given user:
      | Username    | Pally Rapamichael      |
      | First name  | Pally                  |
      | Family name | Rapamichael            |
      | E-mail      | p.papamichael@pally.de |
    And the following "contact_information" content:
      | name       | email                  |
      | Klim Smith | klim.smith@example.com |
      | Raul Timon | raul.timon@example.com |
    And the following email confirmation request:
      | email                  | status    |
      | klim.smith@example.com | confirmed |
      | raul.timon@example.com | confirmed |
    And the following "collection" content:
      | title                | contact information |
      | Antiruin development | Raul Timon          |
    And the following "solution" content:
      | title                    | abstract                        | description                     | topic                   | solution type                      | collection           | contact information |
      | One exclusion advantages | Separating molecules by height. | Separating molecules by weight. | Demography, E-inclusion | Non-binding Instrument, Data Owner | Antiruin development | Klim Smith          |
    And the following users:
      | Username    | E-mail         | First name | Family name |
      | jmacc_local | jodine@ankh.am | John       | Maccalariat |
    And CAS users:
      | First name | Last name   | Username | E-mail         | Password | Local username |
      | John       | Maccalariat | jmacc    | jodine@ankh.am | 10d1ne   | jmacc_local    |

    Given I am logged in as a moderator
    Then I go to "/collection/antiruin-development/solution/one-exclusion-advantages/contact"
    And I should see the heading "Contact us"

    When I go to the edit form of the "One exclusion advantages" solution
    And I press the button "Contact us" in the "Description" wysiwyg editor
    And I press "Confirm"
    And I press "Publish"
    And I should see the heading "One exclusion advantages"
    When I click "Contact us"
    Then I should see the heading "Contact us"
    Then I fill in "Your name" with "Lina Example"
    And I fill in "Your e-mail address" with "linak@example.pt"
    And I fill in "Subject" with "New reclamation about Owner"
    And I select "Other" from "Type of support"
    And I fill in "Message" with "This is reclamation test."
    When I press "Send e-mail"
    And I should see the success message "Your message has been sent."
    Then I should see the heading "One exclusion advantages"
    And I should be on "/collection/antiruin-development/solution/one-exclusion-advantages"

    When I am an anonymous user
    Then I go to the "One exclusion advantages" solution
    And I click "Contact us"
    And I should see the heading "Sign in to continue"

    When I fill in "E-mail address" with "jodine@ankh.am"
    And I fill in "Password" with "10d1ne"
    And I press "Log in"
    And I should see the heading "Contact us"
    Then I fill in "Your name" with "V Example"
    And I fill in "Your e-mail address" with "vnak@example.pt"
    And I select "Other" from "Type of support"
    And I fill in "Subject" with "v2 New reclamation about Owner"
    And I fill in "Message" with "This is v2 reclamation test."
    When I press "Send e-mail"
    Then I should see the success message "Your message has been sent."
    And I should see the heading "One exclusion advantages"

    # Check layout paragraphs.
    Given the following "custom_page" content:
      | title                 | collection           | abstract |
      | Contact us text check | Antiruin development | N/A      |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Contact us text check" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Contact us text check" "custom page"
    And I click "Choose component"
    And a modal should open
    # Not used anymore.
    And I should not see the link "Simple paragraph"
    And I click "Text"
    And the "Contact us" button should be available in the "Body" wysiwyg editor
    And I press the button "Contact us" in the "Body" wysiwyg editor
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the success message "Custom page Contact us text check has been updated."
    And I should see the link "Contact us"

    When I am an anonymous user
    Then I go to the "Contact us text check" custom_page
    And I should see the link "Contact us"

  # For 'Media video with CKEditor5' see tests/features/video/embed.feature
