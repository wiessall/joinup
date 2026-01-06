@api @group-clone
Feature: Webforms

  # @covers \joinup_webform_webform_access_rules_alter()
  Scenario: Moderators cannot administer a webform delivered by config sync but
    they are able to create forms via UI.

    Given I am logged in as a moderator
    When I go to "/admin/structure/webform/assessment"
    Then the response status code should be 404

    # But they are able to manage webforms created via UI.
    When I go to "/admin/structure/webform"
    And I click "Add webform"
    When I fill in "Title" with "Hello World!"
    And I fill in "Machine-readable name" with "hello_world"
    And I press "Save"
    Then I should see "Please add elements to this webform."

    When I go to "/admin/structure/webform"
    Then I should see the link "Hello World!"
    When I click the "Build" link in the "Hello World!" row

    # Covering actual webform building.
    Then I click "Add element"
    And I click "Checkbox"
    And I fill in "Title" with "Test checkbox"
    And I fill in "Key" with "checkbox_value"
    And I press "Save"
    Then I should see the link "Test checkbox"
    And I should not see "Please add elements to this webform."

    # Long query string is a regression test covering ISAICP-10576.
    Then I go to "/webform/hello_world?ad9=cb7114&adf=9a06cc&af0=d956a8&amz=15befb&ayc=d534b1&ayk=29a4ef&azl=963be5&azs=4a54d7&b00=351c9d&bhz=ff9a9a&bjl=9af72d&bjz=9c15d2&blq=33557f&c5j=9d1df4&cav=0ed5f1&cb1=11f229&cbg=268dde&cfm=7e27d6&chi=0c820f&chk=761c5c&cj1=ddf81c&cli=b0b7b5&co9=e4f747&cq1=727d2f&cs8=ebd1d2&cso=cbda7a&cu2=8f9701&d56=76b4ed&d6k=3fef97&daf=bdb27a&dcy=248d36&dey=271a55&dfi=c32a01&dgv=53111b&dj6=48f0d5&dk3=715d94&dmi=df4ece&dni=bd4d7d&ds4=2f59bd&e0t=9a6431&e50=133d2d&efe=9df05f&ekm=50b596&ent=fb779c&eq5=0f3af7&ezm=80e2a2&f08=cd693b&f1i=64f731&f4q=6c3787&f5z=8a48b6&f9g=4988f6&f9z=038cd1&fch=e97260&fj9=a55466&fja=0789a7&fox=ffe400&fxr=538ee8&g4d=a72355&g9o=016ffa&geb=0f5f50&gnh=f9ec61&h1z=bfbfe8&h5o=66c511&h75=3e593f&has=32f835&hic=5fafa3&hsh=b927ab&hu1=b445bc&hul=8873b6&hwa=876b50&i6m=383a4c&i7b=ddd775&iao=11d7dd&idg=8a6599&idr=4378b7&iec=a3b983&ig7=96ebcb&ima=bc56cf&ism=d50f1f&ium=7539d6&iz1=a8dc69&jaf=3a8416&jbo=332da4&jeh=428be6&jgw=cb6d74&jkx=fb576a&jof=189704&jxl=dcd9e9&jzq=fa1db3&k3g=5bd102&k95=320f30&kab=7df4ab&kbx=8eb136&kfg=f08c31&kmf=400cce&kmi=c2a7c1&kmj=570d28&kng=998853&kpk=b25184&kzg=84975d&l2g=993ce1&l2l=8bdfab&l8y=94ceac&la4=d46813&lhw=56f7f7&lpp=e25f26&lvu=fab0f8&lzo=4e7fd6&m10=0d5356&m1i=f2eefe&m7m=db48e0&mea=2a9d62&meq=1d97da&mhk=137c30&mmc=d028d8&mny=1c733a&mon=c931ad&mr1=180950&mr5=cdf23a&msa=f1c629&mt4=ca08f3&mu9=e651cd&myi=98ee22&mzo=e57755&n01=b265af&n1y=bafe79&n3y=9ec027&n5i=dec71f&n6p=b0f3af&n6q=bbabb0&nah=4c1ded&ncn=5d49e1&ne5=e1b60b&ng6=ac5e22&nhm=80829c&nih=e8e807&nk8=7afda3&nr7=7f2e38&nt8=b1aca9&nv9=3560c7&nwj=cc1eaa&oaz=50b4d9&oev=c4e0f9&ofs=ea23f9&oit=e3074d&okc=fe7ca7&om1=0f7743&ono=13e7cf&op3=04de78&os4=718b58&ovt=500670&p5x=611dfc&p9r=c7fa43&pbi=ed1a9b&pdj=7048e1&pew=8aefc6&poq=41c353&pp9=112eb0&ps0=3f1237&pvg=366e76&pzs=4da592&q01=dbcbd6&q3a=10cc04&q5c=787221&q5j=c58d67&q5k=0f62a4&qgz=758651&qia=66f506&qio=73c9d1&qmo=44bb2b&qn1=092dab&qs6=0d8fd0&qua=348eca&qx7=2f0e23&qy4=f32cb3&r4j=d7655b&r51=7b8952&rf0=74d561&rgm=f4cb6a&rl8=707fe1&rok=ef9f73&rr0=7ded45&rx5"
    And I press "Submit"
    Then I should see the text "New submission added to Hello World!."

    When I go to "/admin/structure/webform"
    And I click the "Delete" link in the "Hello World!" row
    And I check "Yes, I want to delete the Hello World! webform"
    And I press "Delete"
    Then I should see the success message "The webform Hello World! has been deleted."
