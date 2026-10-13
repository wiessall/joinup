# Current situation

As far as I understand:

-   All @fao.org and @welthungerhilfe.de users should be able to sign on using the central CSI keycloak instance.
-   My testing has shown that I can also log on using my private github account
-   On the STI portal client side I can enable the option to automatically create a user account for everyone successfully logging on through keycloak


<a id="orgde36a7d"></a>

## Desired behavior

Only fao.org and welthungerhilfe.de (WHH) accounts can log on to the STI portal using Keycloak SSO.

Again, my testing has shown that I can register a private github account on FAO Keycloak without problems and then go ahead and auto-create an account on the STI portal. This poses a security risk for our website


<a id="orgca7c9b8"></a>

## Potential Solutions

1.  Disable google and github on keycloak
    -   only FAO / WHH get accounts on keycloak and subsequently on the portal
2.  Create user groups on keycloak: One group FAO, another one WHH, another one e.g. 'external'
    -   on the sti portal I can map roles & permissions to keycloak groups: FAO -> maintainer; WHH -> STF tester; 'external' -> no permissions
3.  Create a whitelist of WHH accounts
    -   on the STI portal I can create accounts for pre-determined emails & make sure that only existing accounts can successfully log on using keycloak SSO
    -   This is not optimal, since WHH might have trouble procuring a complete list of candidates and I would have to create thousands of accounts semi-manually

