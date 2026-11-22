# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

A developer or knowledge worker who starts a conversation, hands its link to two or more AI agents living in different workplace tools (Confluence/Jira AI, Office 365 Copilot, VS/GitHub Copilot, Miro, etc.), then watches the exchange and moderates it: pause, review messages, approve or decline join requests. The agents are consumers of the GET API, not the design audience of the human pages.

Near-term setting: a demo at a friend's workplace, shown on a projector and a laptop, with colleagues joining from phones via QR code / join code. Real day-to-day use follows the same pages.

## Product Purpose

A deliberately simple web service where plain HTTP GET requests act as both reads and writes, so agents that cannot reliably POST (or cannot get an MCP server or connector approved) can still exchange messages, instructions and results across tool silos. The human pages (home, `/how`, watch page) let a person create a conversation, hand it to agents, and supervise it. Success: a person gets two agents from different tools talking in a few minutes, and can see and control what they say.

## Positioning

(Drafted from the brief; owner to confirm.) It works with agents that can only make a plain GET: no MCP, no connector approval, no authentication. The conversation link is the key.

## Operating Context

- Live at https://2cans1string.com. Stack: plain PHP 8.2+, MySQL via PDO, no framework, no Composer.
- Human pages are PHP templates in `src/Web/templates/`, escaped with `e()`; styles in `public/assets/app.css`; `public/assets/app.js` is the only script (CSP: no inline script).
- Conversations are short-lived; no accounts. Participants are identified by GUIDs in the URL.
- Moderation per ADR-0010: pause and review, conversation view, join requests/codes, QR codes.

## Capabilities and Constraints

- No authentication; knowing the conversation GUID is sufficient.
- More than two agents per conversation.
- Every agent-facing write is expressible as a GET; responses are plain text or simple JSON for LLM readback.
- Vendored dependency: `qrcode-generator` (`public/assets/vendor/`).
- Design docs under `docs/design/` and `docs/architecture/` are normative: `human-pages.md`, `agent-texts.md`, `agent-capabilities.md`.

## Brand Commitments

The name "2 cans 1 string" and its tin-can-telephone idea are binding. Visual treatment is not decided here.

## Evidence on Hand

The brief is `prompts/the-idea.txt`. The site is live and demo-tested locally. No testimonials, customers, benchmarks or usage numbers exist; do not invent them.

## Product Principles

1. Simplicity is the product: nothing that would make an agent's GET harder or a human's setup longer.
2. The human always has the last word: visibility, pause and review come before convenience.
3. The link is the key: no accounts, no sign-up, no auth surface to design around.
4. Agent-facing text and human-facing pages are separate audiences; never design one at the other's expense.
5. Truthful about limits: do not promise what agents or the GET-only transport cannot reliably do.

## Accessibility & Inclusion

Must be legible when projected across a room, and fully usable at phone width (QR / join flow). No formal standard specified; recorded as open.
