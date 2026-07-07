# ADR-001: Identity authority and SSO strategy

**Status:** Proposed  
**Date:** 2026-04-14  
**Deciders:** [to be filled]  
**Context area:** Cross-system authentication and identity management

## Context

The STI platform consists of three independent systems: STI Social (Open Social/Drupal), STI Portal (Drupal), and SteveBot (custom TypeScript/Python chatbot). Currently there is no shared authentication — users have separate accounts on each system, and the only connection between Social and Portal is a hyperlink.

Users include staff from institutional partners (currently FAO and Welthungerhilfe), as well as independent researchers and practitioners who register directly. Institutional partners manage their own identity infrastructure (e.g. FAO uses Microsoft Entra ID).

Content on the STI Portal is gated by organizational affiliation — users from different organizations see different content. This access control currently has no connection to the identity system.

## Decision

**We will introduce Keycloak as a centralized identity broker for the STI platform.**

- Keycloak becomes the single OIDC provider for all three systems (Social, Portal, SteveBot).
- STI Social remains the authority for user profiles, group memberships, and affiliation data.
- Institutional partners (FAO, WHH) are configured as federated identity providers in Keycloak via OIDC.
- Users without an institutional IdP create local accounts through Keycloak, with profile data stored in STI Social.
- Keycloak issues JWTs containing a standardized `affiliation` claim derived from either the federated IdP connection (hardcoded per IdP) or the user's profile in STI Social (queried at login time via API).

## Alternatives considered

### Direct OIDC federation from Open Social (no Keycloak)

Open Social is configured as an OIDC client for each external IdP directly. Portal and SteveBot are configured as OIDC clients of Social.

- Pros: No additional infrastructure. Fewer moving parts.
- Cons: Every new institutional IdP requires reconfiguring Social. Social must act as both identity authority and OIDC server simultaneously. No clean separation between local accounts and federated accounts. Login page becomes cluttered with IdP-specific buttons managed inside Drupal.
- Why rejected: Does not scale beyond 2-3 institutional IdPs. Mixes identity brokering concerns into the community platform.

### External managed identity service (Auth0, Okta)

A commercial identity platform replaces Keycloak.

- Pros: Managed infrastructure, no self-hosting. Polished UI. Enterprise support.
- Cons: Recurring cost scales with users. Data sovereignty concerns (user data stored outside our infrastructure). Vendor lock-in on a core platform capability.
- Why rejected: Budget constraints and data sovereignty requirements of institutional partners make self-hosted Keycloak the better fit. Can be reconsidered if operational burden of self-hosting Keycloak proves too high.

## Consequences

- All three systems must implement OIDC client configuration (OpenID Connect module for both Drupal sites, OIDC middleware for SteveBot).
- Keycloak becomes critical infrastructure — if it is down, no one can log in to any system. Requires monitoring and a recovery plan.
- A shared affiliation taxonomy must be established so that JWT claims map consistently to content access rules across all systems (see ADR-002).
- SteveBot should implement OIDC redirect support from day one, even if initial launch is anonymous-only, to avoid a costly retrofit.
- STI Social's JSON:API must be accessible to Keycloak for querying user profiles at login time.

## Implementation notes

- Keycloak "hardcoded claim mapper" per IdP connection handles institutional affiliations (FAO IdP → `affiliation: "fao"`).
- For local accounts, a Keycloak "user attribute mapper" reads the affiliation from Social's API response.
- Drupal's OpenID Connect module maps the `affiliation` claim to Drupal roles, which gate content visibility via existing access control modules.
