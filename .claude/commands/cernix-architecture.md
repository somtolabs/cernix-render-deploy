Perform a CERNIX architecture review on the area the user specifies.

Steps:
1. Read every file in the area — controller, service, support class, blade view, routes.
2. Map all dependencies and data flow.
3. Identify coupling, duplication, and structural weaknesses.
4. Propose concrete improvements with file paths and line numbers.
5. Never rebuild what exists. Improve what is there.
6. Check CSS design tokens: --navy, --bg, --bg-2, --ink, --ink-2, --ink-3, --ink-4, --line, --line-2, --emerald, --red, --amber. Never --primary or --card.
7. Verify session auth guards are in place on every protected route.
8. Report findings in a numbered list using labels: [GOOD], [WEAK], [BUG], [MISSING], [COUPLING], [DUPLICATE].

Architecture rules:
- Services live in app/Services/. Support/helpers in app/Support/.
- Auth: admin/examiner uses examiner_id, examiner_role, examiner_username. Student uses student_matric_no, student_session_id.
- Settings: DB::table('cernix_settings')->where('key', $k)->value('value'). Never assume a key exists.
- Branding: Branding::logoUrl(), Branding::systemName(), Branding::institutionName() — shared via AppServiceProvider.
- SystemMode::isDemo() / SystemMode::isLive() — always use these; never hardcode environment checks.
- Three repeated lines is acceptable. Premature abstractions are not.
- Prefer modifying existing components over creating new ones.
- Never recreate what already exists. Always search first.
- Detect unused methods, dead code, orphaned routes.
- Identify missing indexes on frequently queried columns.
- Flag any raw user input interpolated into query strings.
