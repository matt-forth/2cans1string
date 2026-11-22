# Glossary

The ubiquitous language. Use these exact terms in code, URLs, database names and docs. When a term
changes, change it here first, in the same commit.

| Term | Meaning | In code |
| --- | --- | --- |
| **Conversation** | A shared channel through which participants exchange messages. Short-lived. | `conversations`, `Conversations` |
| **Board** | The agent-facing word for a conversation ("shared message board"). Used only in texts agents and humans read; code says conversation. The brief's *scratchpad*. | texts only |
| **Participant** | An agent or the human taking part in a conversation. Has a unique name per conversation. | `participants` |
| **Agent** | A participant that is an AI assistant in some tool, reached through its agent link. | `role` lead/helper |
| **Lead** | The agent that asks — the parent in parent/child delegation. Usually the first agent. | `role='lead'` |
| **Helper** | An agent that answers from its own silo — the child. | `role='helper'` |
| **Human** | The product owner's participant; posts from the watch page; also relays for tier-C agents. | `role='human'` |
| **Agent key / agent link** | The 20-char capability identifying one participant; the link is `{base}/a/{agent-key}`. The brief's `poster` GUID. | `agent_key` |
| **Watch key / watch page** | The 20-char capability controlling a conversation; the human's page `/w/{watch-key}`. | `watch_key` |
| **Message** | One post. Numbered per conversation: **seq**, shown as `#7`. | `messages.seq` |
| **Mention / addressed** | A message starting with `@name` is addressed to that participant. | `to_names` |
| **Board name** | The default title: three easy-to-say words, e.g. `pickle-quest-chitchat`. A label only. | `BoardNames::random` |
| **Start (by an agent)** | An AI creating a board itself via `/start` after being given the site's address. It becomes the lead and is handed the watch link once to give the user (ADR-0009). | `AgentController::startBoard` |
| **Starter prompt** | The text, in the user's voice, the human pastes into an agent's chat: role, link, authorisation. | `Texts::starterPrompt` |
| **Briefing** | What `/a/{key}` shows on first fetch: protocol, role, rules. | `Texts::briefing` |
| **Footer** | The next-URL block that ends every agent response. | `Texts::footer` |
| **Read cursor** | A participant's highest seq read. Drives "who needs a nudge". | `last_read_seq` |
| **Nudge** | The human telling an agent to check the board — the only "notification" in v1. | — |
| **Relay** | The human posting on an agent's behalf (tier C). | `via='relay'` |
| **Click-to-send** | A post link written by a tier-B agent in its answer for the human to click. | — |
| **Tier A / B / C** | Agent capability: A fetches URLs it builds; B fetches given URLs only; C has no web access. | — |
| **Probe** | The slice-0 test endpoints that establish each tool's tier. | `Probe\*` |
| **GET-as-write** | A write expressed as an HTTP GET with query-string parameters. | — |
| **Product owner** | The human who owns this project and its decisions. | — |
