# STI feature request intake policy

**Status:** Draft v0.1 — for review
**Owner:** [Tristan Wiessalla]
**Approved by:** [Manager / sponsor — pending]
**Review cadence:** Quarterly, or when a major ADR lands
**Last updated:** 2026-04-16

---

## 1. Purpose

The STI platform receives feature requests from multiple directions: FAO internal teams, partner organisations, workshop participants, and management. Without a shared intake process, every request becomes an individual negotiation, duplicates accumulate, and architectural decisions get made implicitly under feature-request pressure rather than deliberately.

This policy sets out how requests are triaged, who decides, and what a requestor can expect. It is deliberately short — the intent is that it be followed, not consulted.

## 2. Scope

Applies to new feature requests for: ATIO KB (STI Portal), STI Social, Challenges, SteveBot.

Does not apply to:

- **Bug reports** — handled through the usual defect channel.
- **Content additions** (new innovations, organisations, events) — editorial workflow, not feature requests.
- **Infrastructure and DevOps changes** — handled outside this policy.
- **Architecture decisions themselves** — handled via the ADR process. Feature requests can *trigger* an ADR but do not *substitute* for one.

## 3. Submission

All requests go through [shared intake channel — e.g. a dedicated email alias, a ticketing queue, or a form]. Requests made in meetings, chat DMs, or hallway conversations are not triaged; the requestor is asked to submit through the channel.

A submission must include:

- **Who** will use the feature (named role or team)
- **What problem** it solves (not the feature itself)
- **Which product** it's for, if known
- **Who pays / owns** — funding source or owning unit, if known

Submissions missing any of the above are returned with a template and a note on what's missing. They are not declined.

## 4. Triage outcomes

Each submitted request receives one of four outcomes within [5] working days. The decision is recorded in a request log visible to [the team / stakeholders].

### 4.1 Accept and schedule

The request fits the current content model, targets a product with a named owner, and has a clear user. Goes to the product's backlog with a priority assignment. No commitment on delivery date.

### 4.2 Defer to architecture decision

The request implies an architectural question that has not yet been decided. The request is **paused, not refused**. The requestor receives:

- The ADR reference the request depends on
- The current status of that ADR
- A note that their request counts as stakeholder input to the decision, and they will be notified when it lands

### 4.3 Reframe

The request is asking for a new field or content type, but the underlying need is better modelled as an evaluation framework, a controlled vocabulary term, or a relationship. The requestor receives the redirect and a pointer to the relevant mechanism. If the mechanism does not yet exist, this becomes a 4.2 instead.

### 4.4 Decline

The request duplicates an existing FAO platform (TECA, AGRIS, AGROVOC, WOCAT, etc.), is out of scope for any current product, has no identified owner willing to fund or run it, or targets a vision-tier concept that has not been scoped as a real product. The requestor receives a written explanation and, where possible, a pointer to a more appropriate platform.

## 5. Decision authority

Triage decisions are made by the policy owner.

Appeals go to [Manager / sponsor] and are resolved **in writing**, not in meetings. The outcome — overturned or upheld — is recorded in the request log. A pattern of overturned decisions is a signal to revise the policy, not to abandon it.

## 6. What this policy does not do

- It does not prioritise across accepted requests — that's the product owner's call.
- It does not evaluate technical feasibility — that's the implementation phase.
- It does not commit to a timeline for anything. "Accepted" means "in the backlog," not "scheduled."

## 7. Amendment

Amendments are proposed as a diff against this document and require approval from [Manager / sponsor]. Amendments triggered by an accepted ADR are recorded with the ADR reference.

---

## Appendix: worked examples

**A. "Team X wants a TRL level dropdown on the innovation content type."**
→ **Defer (4.2).** TRL is one evaluation framework among many. Adding it as a field hardcodes it. Depends on ADR-002 (content model) and ADR-003 (evaluation frameworks). The team's input on TRL criteria is welcome as part of ADR-003.

**B. "We need to filter innovations by funding organisation."**
→ **Accept (4.1).** Organisation-to-innovation relationships exist in the current model. Standard view work.

**C. "Can we add an Agro-AI training module to the portal?"**
→ **Decline (4.4).** Training is a different product family with different users, owners, and funding. Not STI Portal scope. Redirect to [appropriate venue].

**D. "Let's add a 'climate zone' taxonomy."**
→ **Reframe (4.3).** AGROVOC covers climate zones. Reuse existing terms rather than build a parallel taxonomy. If AGROVOC coverage is insufficient, that's a conversation with the AGROVOC team.

**E. "We need a fundraising dashboard in the portal."**
→ **Decline (4.4).** Vision-tier concept with no owner, no funder, and no data model. Revisit if and when Open Innovation Network is scoped as a real product with an owner.
