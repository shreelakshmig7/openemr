# Memory Bank

Persistent context for this project. The AI and you can use this to keep decisions, facts, and focus across sessions.

## Structure

| File | Purpose |
|------|--------|
| **agent-reference.md** | Single reference: feature decisions, what's done vs left, architecture (openemr-agent) |
| **active_context.md** | What we're working on right now; update when switching tasks |
| **critical_facts.md** | Key facts about the codebase, env, and constraints |
| **decisions.md** | Important decisions and rationale (ADRs light) |
| **glossary.md** | Project-specific terms and abbreviations |

## How to use

1. **Starting a task:** Set the current focus in `active_context.md`.
2. **After a decision:** Add a short entry to `decisions.md` with why.
3. **Useful discovery:** Add to `critical_facts.md` or `glossary.md`.
4. **Reference in chat:** Say "check the memory bank" or "@memory-bank" so the AI reads the relevant file(s).

Update these files as the project evolves so they stay accurate and useful.
