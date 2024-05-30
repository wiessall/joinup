@api @group-clone
Feature: Duplicated EIF links

  Scenario Outline: Only one EIF Perspective link at publicly accessible groups
    Given I go to "<url>"
    Then I should see the link "EIF Perspective"
    And the link "EIF Perspective" should appear 1 time in the "Quick links" region

    # Note: Interoperability Test Bed is also affected, but it has a custom
    # landing page, so its Quick links menu cannot be checked.
    # See /rdf_entity/http_e_f_fec_ceuropa_ceu_fisa_factions_fisa2_f08_bsupporting_binstruments_bfor_bpublic_badministrations_f25action_aen_chtm
    Examples:
      | url                                                                                   |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8728c677_b7764_b4837_b9706_b9f8aab19215f |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fac376c94_b74cf_b4dd7_bade7_b267d6a4ec4dc |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe4618445_b6199_b42fc_b94df_b0b0395fd7878 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f14f29444_b498b_b4d43_ba77b_ba6219907cb44 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f23d19769_b2645_b4773_ba23c_b86b5ba85ac47 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fcf56105e_b9833_b4eba_bbcca_ba45a5fa0b28f |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f7b67948e_b9d40_b4406_b9262_bbb7876ecf56e |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_ffa77f20e_badc9_b4dd3_bb254_bf979401af316 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fcd809bd0_b0a5a_b4bc2_b8c0b_bceeb7e17fa3d |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fd67fe778_b4991_b4425_b8d0b_b462e2b149872 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fee03d5d3_bd705_b4e40_b8f82_b038045ce3121 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f3a3c5fb5_b9aa5_b4730_b84cf_bab8dd08817a1 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f784726cb_b31ef_b47bb_b8eed_bc323f0f23e75 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f22514c30_b2966_b4820_b8a88_b11db42868ee8 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f1a8ccc8b_b344b_b462d_b97a4_be65ea8063819 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f08d20e40_b909d_b457d_bbc96_bd49c54ee1980 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f39db9ce5_b2f1c_b422c_ba3ce_b19203b431b5c |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f367712ad_b2a21_b4ac7_ba61d_bf1084c488490 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_feee9575f_b51b3_b45aa_b80de_b9427cafc6905 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f06f60848_b8434_b4fe9_b837d_b1a93103be785 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f385ad5b8_b2f7d_b4347_bbeae_bc5736cb14218 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fdfba1169_b806f_b4c9e_ba42e_ba5c5830a2221 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f31929cda_bc61a_b4631_b9ef3_bd35c1c3495b3 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f0be9ab26_b53a2_b4285_bb4ee_bbef0faeddf3b |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe1f84ab1_bbad6_b4e95_bb37a_be54ba7dfc551 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f2c930c7b_b5e2f_b4954_b8522_bbd3411339d6c |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_ff4ae84dc_b714b_b43bc_ba275_be48704ac79ea |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f7080193c_b9060_b4f51_bbde9_b45935b984c75 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fd0cec8bb_b38d2_b414e_b9d72_ba080f8bf85c1 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f13a44ddc_be006_b4b71_b9191_b8cb484fc1f0c |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe255c520_b89b7_b4321_bb5b8_b334f731267f3 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fbadeee45_b24e0_b41f0_bbe4b_bcb8f5e7267b2 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8c98e234_bbed8_b435f_ba693_be69cea57f9f5 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f7b90cdea_b9343_b43ed_bb49f_bff62510f10a1 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe57137e7_b0781_b43c1_ba974_b07b646e01a32 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_ff3b16d2b_b2ff0_b4d72_b8656_b2695b41d5343 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f19849123_b8299_b4c3c_b96aa_b1c6c6893c0a7 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f36d6db9d_bac18_b4124_ba25d_b1d3144e52f56 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f77e86bad_ba52b_b44b2_bbd86_bc8c8eb308e0a |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f62d4239a_b111e_b4468_bb83b_b8687a27f8497 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe041e1e5_b927c_b423e_b9c88_b69bb44e1ee9f |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe85ab75d_bf919_b4ecd_b8bb3_be8faac6a9e40 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fbf99e6e4_b8530_b44b9_ba69e_b4433366692ec |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f2858dc5a_b05e3_b453a_b8c59_bc1c62743f9e6 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f26ee4968_b764d_b4549_bacf2_b3664e3d39b43 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f15281ef9_bd3c2_b4d05_b91b6_b9f0145423b64 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f4d873268_b28ca_b4531_b9559_bb4b6f8762ed6 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f4c9fd394_bca7d_b4850_b9530_b636afd11b998 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8e942bc2_b657a_b4289_bb057_bf2a285ee7375 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe145ff68_b40e3_b423b_baa31_ba223e41abd48 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f9e22150f_b5fff_b45bd_bac16_b34d494b9b690 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f89919979_b1e4e_b4376_ba55e_b8ae1547c974e |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f930aaef3_be02c_b4836_ba2ed_b9d1a4e0a80b9 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8f67de7b_b9478_b49fa_bb665_b833dcb82e255 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fd401e45a_ba8f1_b4ed1_ba775_b3e3c56633c4a |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f38452c2f_b0319_b44eb_bba65_bb4da6b68cf68 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f3d7b8498_b9fe5_b484a_b99f4_bf21a7c3a90d8 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f64b58c13_baa7f_b45f5_b8f02_be3b481bd8635 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f40f0a731_bb9da_b4eb1_bba14_b0ddbd5aaae12 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8e806869_bab3f_b42a7_b9ac5_bc5e1c5ea1631 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f266794d8_bf476_b4046_ba81b_b21c307d0b8bd |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f7e094dd0_b64c4_b4dbd_baf09_bc99fe7c5ca97 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fd6618384_b173c_b4a91_ba74e_bf0a6379142c1 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f2fa2d97b_b554a_b4c1f_b8784_b960b1b4207b5 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fff1c8ada_b5f24_b4e02_b930e_b81983efa58f7 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f2770478a_bbcf0_b4d8f_bb734_bb594157cd071 |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f7dbb23b8_bb567_b4466_b9d4c_b3cd7c9e3607a |
      | /rdf_entity/http_e_f_fec_ceuropa_ceu_fleos                                            |

  Scenario Outline: Only one EIF Perspective link at non-public groups
    Given I am logged in as a moderator
    Given I go to "<url>"
    Then I should see the link "EIF Perspective"
    And the link "EIF Perspective" should appear 1 time in the "Quick links" region

    Examples:
      | url                                                                                                                                    |
      | /rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f037cdf00_b2483_b4322_bb2d6_b392c27f60e86                                                  |
