# CERNIX Local Demo Data

This note describes the local/demo data shape used by CERNIX. It is intended for maintainers and academic testing, not as a public credential sheet.

In production, students use real university records and real payment references. Demo payment references work only when demo mode is enabled through the environment.

## Demo Matric Pattern

CERNIX now builds the matric number from the registration selections instead of asking the student to type the full number.

Format: `YY FF DD NNN`

Example: `220404008`

- `22` = level/year code
- `04` = Faculty of Computing code
- `04` = Computer Science department code
- `008` = student number

Level/year codes:

- 100 level = `25`
- 200 level = `24`
- 300 level = `23`
- 400 level = `22`

Faculty code:

- Faculty of Computing = `04`

Department codes and fixed school fees:

- Computer Science = `04` = `N100,000`
- Software Engineering = `05` = `N120,000`
- Information Technology = `06` = `N110,000`
- Cyber Security = `07` = `N140,000`
- Data Science = `08` = `N150,000`

## Student Number Range

The current demo passport set supports student numbers `001` through `014`. The registration UI should show a clear message if a demo student number is outside the available photo range.

Passport files are stored locally under:

```text
public/demo-passports/
```

The application should reference them with relative public paths such as:

```text
demo-passports/student-001.jpg
```

Do not use Windows file paths, temporary upload paths, external portrait URLs, or RandomUser photos at runtime.

## Demo Payment Behavior

Demo payment references are accepted only when demo mode is enabled. The demo payment shortcut confirms a demo school-fee payment for testing, but it does not bypass identity validation, department/level validation, QR security, exam pass approval rules, scanner verification, or production Remita behavior.

For real production deployments, keep demo mode disabled.

## Privacy Note

Demo passport photos are local mock assets used for controlled testing. They are not real AAUA student records.

Project/team photos belong in project documentation only and must not be used as student identity or passport photos.
