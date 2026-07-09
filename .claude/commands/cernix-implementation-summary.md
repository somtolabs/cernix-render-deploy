Output the CERNIX implementation summary block at the end of the current task.

Every response that makes code changes MUST end with this exact block:

---
## Implementation Summary

**Changed:** [one-line description of what was implemented or fixed]
**Files modified:** [list each file path]
**Bugs fixed:** [list each bug, or "none"]
**UI improvements:** [list each improvement, or "none"]
**Logic improvements:** [list each improvement, or "none"]
**Remaining known issues:** [list any known gaps, or "none"]
**Suggested next step:** [one sentence on what to tackle next]

Rules:
- Never omit this block after a code change
- Keep each bullet concise — one line per item
- Do not repeat content already in git commit messages
- If no code changed, omit the block entirely
- "Suggested next step" must be actionable and specific
- List every file modified, not just the "main" ones
