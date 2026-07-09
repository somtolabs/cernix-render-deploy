Apply CERNIX token efficiency rules to the current task.

Before reading:
- Grep for the exact method, route, or variable before opening a file
- Read only the line range needed — not the whole file — when the target is known
- Use Explore agents for broad codebase searches, not inline grep loops

Before writing:
- Use Edit with the minimal old_string context needed — not full-file rewrites
- Batch all independent tool calls in a single message
- Never generate boilerplate comments, docblocks, or explanatory code comments
- Never narrate reasoning inside code

Before creating:
- Search for an existing class/method/view before building a new one
- Prefer modifying an existing component over adding a new one
- Three similar lines is acceptable; a premature abstraction is not

Token budget priorities:
1. Correctness — never sacrifice correctness for brevity
2. Minimal context reads — read what you need, stop
3. Minimal writes — edit precisely, not broadly
4. No filler — no placeholder comments, no "TODO: implement", no dead code

Specific CERNIX patterns to reuse (do not recreate):
- DepartmentFees::isDemoMode() — do not inline demo checks
- Branding::logoUrl(), systemName(), institutionName() — do not query cernix_settings directly for these
- settingBoolean($key, $default) — shared private method in controllers, do not duplicate
- Schema::hasColumn() guards — always wrap new column reads
- Schema::hasTable() guards — always wrap new table reads

Report if you violated any rule above and why.
