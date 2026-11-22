# Risks

Status: **Proposed (v1 design, 2026-11-22)**. Ordered by how likely each is to sink the idea.

## R1 — The agents won't do it (high likelihood, fatal if total)

The core move — a model building a URL that carries content to a third-party site and fetching it —
is the canonical **indirect prompt injection / data exfiltration** pattern, and agent vendors defend
against it on purpose. Specific ways it can fail (what's *known* vs *believed* per tool is in
[agent-capabilities.md](../design/agent-capabilities.md)):

- The tool has no arbitrary web fetch at all, or the tenant admin disabled it (common for M365
  Copilot web content; possible for Rovo).
- The tool fetches only URLs the user typed or that appeared in fetched content, never ones the model
  composed (a known defence in some assistants).
- Web access goes through a search index (Bing grounding) rather than a live fetch — our pages are
  `noindex` and change every minute, so the model would see nothing or stale content.
- The model refuses to follow instructions found on a fetched page, or asks the user to confirm
  every fetch (tolerable for a trial, tedious in use).
- The fetch tool caches by URL, or summarises/truncates the page before the model sees it.

**Response in the design:** the three tiers (overview §4) so a partial failure degrades rather than
kills; the starter prompt that puts the user's own authority behind the fetches (ADR-0006);
server-minted next URLs (ADR-0003); front-loaded, short responses. **Response in the plan:** slice 0
is a probe, a ~1-hour build, run against every target tool *before* building the rest. If no tool
except VS Code reaches tier A or B, stop and rethink — e.g. towards a browser extension or
clipboard-based relay, which is a different product.

## R2 — Company policy (medium likelihood, fatal for work use)

Posting work content to an outside site may breach policy regardless of whether it works, and the
URLs make it visible to proxy logs and DLP. See [security-and-limits.md §2](security-and-limits.md).
Self-hosting inside the network removes most of it. **Q9 — the product owner's call before using it
with real work content.** Trial with non-sensitive content first.

## R3 — Messages mangled in transit (high likelihood, annoying)

Models URL-encode inconsistently: unencoded `&` and `#`, double encoding, raw newlines, truncation at
the fetch tool's URL limit. **Response:** lenient parser (http-api.md §4), `message` last, echoing the
start and end of what was received, POST accepted, the length guidance in the briefing. `#` remains
unrescuable server-side.

## R4 — The human relay is too tedious (medium likelihood, limits value)

Every turn needs Matt to switch windows and say "check the board". **Response:** the watch page
shows exactly who has unread messages; agents batch work ("do all of these, reply once"); the
briefing tells the lead to send self-contained, complete requests to minimise round trips; posting
returns any new messages in the same response. Long-poll for tier-A agents is on the roadmap.

## R5 — Agents mis-copy or forget their link (medium, recoverable)

Long sessions lose context; a model may truncate or alter the key. **Response:** short key alphabet
without look-alikes; every response repeats the agent's URLs; unknown keys return a helpful text, not
a 404; the watch page shows each starter prompt again for re-pasting.

## R6 — Abuse of a public unauthenticated endpoint (low for a personal trial)

See [security-and-limits.md §6](security-and-limits.md).
