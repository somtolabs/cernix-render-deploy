Audit the CERNIX UI for the page or section the user specifies.

Checklist (report PASS/FAIL for each):
- Spacing: consistent gutters, no collapsed margins, clamp() for responsive sizes
- Typography: page title > section title > label > body > caption hierarchy clear
- Icons: no floating, no orphaned decorative elements, NO emojis anywhere
- Cards: no unnecessary nesting, no cards that are just padded divs
- Tables: min-width guards, overflow-x: auto wrapper, mobile list fallback
- Empty states: informative text, not blank space
- Status badges: use .chip or .admin-status system, not raw colored spans
- Forms: labels on all inputs, hints where needed, error states wired
- Mobile: test at 390px mentally — does the layout collapse gracefully?
- Desktop: test at 1280px — does the layout use available space well?
- Design tokens: --navy, --bg, --bg-2, --ink, --ink-2, --ink-3, --ink-4, --line, --line-2, --emerald, --red, --amber only
- No emojis anywhere (UI copy, Blade templates, JS strings, PHP strings)
- No duplicate information on the same screen
- No dead controls (buttons/inputs that do nothing)
- Navigation: current section highlighted, breadcrumbs where needed
- Headers: page title visible, context clear without reading body
- Filters: functional, state persists after submit
- Loading states: present for async operations
- Error states: shown inline, not just via flash message

After audit, list specific changes with file:line references.
Improve usability first. Never redesign for aesthetics alone.
Preserve CERNIX design language: muted greens, navy, warm off-white, JetBrains Mono for matric numbers.
