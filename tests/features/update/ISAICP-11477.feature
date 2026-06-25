@api @group-clone
Feature: Test ISAICP-11477 update path.

  @update:joinup_core_deploy_201501
  Scenario: Test that licences have the spdx_id
    Given I go to "/collection/eupl/solution/licensing-assistant/find-and-compare-software-licenses"
    Then I should see the following lines of text:
      | AFL-3.0 Academic Free License v3.0                                                                                |
      | Apache-2.0 Apache License, Version 2.0                                                                            |
      | Artistic-2.0 Artistic License 2.0                                                                                 |
      | BSL-1.0 Boost Software License 1.0                                                                                |
      | BSD-2-Clause BSD 2-Clause "Simplified" License                                                                    |
      | BSD-3-Clause BSD 3-Clause "New" or "Revised" license                                                              |
      | BSD-3-Clause-Clear BSD 3-Clause Clear License                                                                     |
      | 0BSD BSD Zero Clause License                                                                                      |
      | CC0-1.0 CC0 1.0 Universal (CC0 1.0) Public Domain Dedication                                                      |
      | CECILL-2.1 CeCILL-2.1                                                                                             |
      | CECILL-B CeCILL-B                                                                                                 |
      | CECILL-C CeCILL-C                                                                                                 |
      | CDDL-1.0 Common Development and Distribution License (CDDL-1.0)                                                   |
      | CPL-1.0 Common Public License, version 1.0 (CPL-1.0)                                                              |
      | CC-BY-3.0 Creative Commons Attribution 3.0 Unported (CC BY 3.0)                                                   |
      | CC-BY-4.0 Creative Commons Attribution 4.0 International (CC-BY-4.0)                                              |
      | CC-BY-ND-4.0 Creative Commons Attribution No Derivatives 4.0 International                                        |
      | CC-BY-NC-SA-4.0 Creative Commons Attribution Non Commercial Share Alike 4.0 International                         |
      | CC-BY-SA-4.0 Creative Commons Attribution Share Alike 4.0 International                                           |
      | D-FSL-1.0 Deutsche Freie Software Lizenz                                                                          |
      | EPL-1.0 Eclipse Public Licence 1.0                                                                                |
      | EPL-2.0 Eclipse Public License 2.0                                                                                |
      | ESA-PL-permissive-2.4 European Space Agency Public License – v2.4 & v2.5 – Permissive (Type 3)                    |
      | ESA-PL-weak-copyleft-2.4 European Space Agency Public License – v2.4 & v2.5 – Weak Copyleft (Type 2)              |
      | ESA-PL-strong-copyleft-2.4 European Space Agency Public License (ESA-PL) - v2.4 & v2.5 - Strong Copyleft (Type 1) |
      | EUPL-1.1 European Union Public Licence, Version 1.1 (EUPL)                                                        |
      | EUPL-1.2 European Union Public Licence, Version 1.2 (EUPL)                                                        |
      | AGPL-3.0-only GNU Affero General Public License v3.0                                                              |
      | GPL-2.0-only GNU General Public License (GPL) 2.0                                                                 |
      | GPL-2.0+ GNU General Public License v2.0 or later                                                                 |
      | GPL-3.0-only GNU General Public License v3.0                                                                      |
      | GPL-3.0-or-later GNU General Public License v3.0 or later                                                         |
      | LGPL-2.1 GNU Lesser General Public License (LGPL) 2.1                                                             |
      | LGPL-3.0-only GNU Lesser General Public License v3.0                                                              |
      | ISC ISC License                                                                                                   |
      | LPPL-1.3c LaTeX Project Public License v1.3c                                                                      |
      | LiLiQ-P-1.1 Licence Libre du Québec – Permissive version 1.1                                                      |
      | LiLiQ-Rplus-1.1 Licence Libre du Québec – Réciprocité forte version 1.1                                           |
      | LiLiQ-R-1.1 Licence Libre du Québec – Réciprocité version 1.1                                                     |
      | MS-PL Microsoft Public License                                                                                    |
      | MS-RL Microsoft Reciprocal License                                                                                |
      | MIT MIT License                                                                                                   |
      | MPL-2.0 Mozilla Public License 2.0                                                                                |
      | ODbL-1.0 ODC Open Database License (ODbL)                                                                         |
      | PDDL-1.0 ODC Public Domain Dedication & License 1.0 (PDDL)                                                        |
      | ODC-By-1.0 Open Data Commons Attribution License (ODC-BY)                                                         |
      | OSL-3.0 Open Software License v. 3.0 (OSL-3.0)                                                                    |
      | PostgreSQL PostgreSQL License                                                                                     |
      | OFL-1.1 SIL Open Font License 1.1                                                                                 |
      | Unlicense The Unlicense                                                                                           |
      | UPL-1.0 Universal Permissive License v1.0                                                                         |
      | NCSA University of Illinois/NCSA Open Source License                                                              |
      | Zlib zlib/libpng License                                                                                          |
