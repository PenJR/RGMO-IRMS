# RGMO-IRMS Alpha Testing Record

Date: September 11, 2026 (Asia/Manila)

Automated local checks passed. Manual alpha testing has not yet been conducted in this record. The manual test cases are prepared for the tester to complete; they are not reported as passed.

## Recorded results

| Check | Actual result | Status | Evidence |
| --- | --- | --- | --- |
| Laravel automated tests | 120 tests passed, 596 assertions; duration 6.08 seconds | PASS | [Raw test output](automated-test-output.txt) |
| Frontend production build | Vite built successfully in 3.32 seconds | PASS | [Build output](frontend-build-output.txt) |
| PHP syntax | 144 files checked; zero failures | PASS | [Configuration checks](configuration-check-output.txt) |
| Composer configuration | composer.json is valid | PASS | [Configuration checks](configuration-check-output.txt) |
| Local database migrations | All 20 listed migrations have run | PASS | [Configuration checks](configuration-check-output.txt) |
| Manual alpha test cases | 24 prepared; zero executed | NOT RUN | [Manual test log](manual-alpha-test-log.csv) |

The automated suite covers authentication, permissions, inventory, requests, projects, notifications, reports, forecasting, profile settings, 2FA, and module rendering. Tests use an isolated in-memory SQLite database. These results apply to the local working files, which contain uncommitted changes. They do not establish that every possible error is absent or that the deployed system has passed alpha testing.

## Conducting manual alpha testing

1. Use a separate test environment with representative sample data and appropriate test accounts. Record its URL or build identifier in the Notes column.
2. Open `manual-alpha-test-log.csv` in Excel, LibreOffice Calc, or another spreadsheet application.
3. Perform each case and fill in Actual Result, Tester, Test Date, Browser / Device, and Evidence File. Add precise input values or additional steps in Notes when needed.
4. Set Status to PASS, FAIL, BLOCKED, or NOT RUN. Use PASS only after observing the expected result. Explain blocked cases in Notes.
5. Record each discovered defect in `defect-log.csv`, assigning an ID such as BUG-001 and linking it from the test case. Include reproduction steps and a screenshot or relevant error output.
6. After a fix, record the retest result and date. Preserve the initial failure and evidence in the defect record.

Suggested severity values: Critical (system unavailable or data loss), High (core workflow blocked), Medium (partial failure with a workaround), and Low (minor display or wording issue).

## Manual completion summary

Fill this section after the testing session:

- Tester(s): ____________________
- Environment / URL / build: ____________________
- Testing dates: ____________________
- Passed: ____ / Failed: ____ / Blocked: ____ / Not run: ____
- Open defects by severity: ____________________
- Retest findings: ____________________
- Alpha testing decision and reviewer: ____________________

Browser interaction, live deployment, email delivery, external AI services, and operational backup restoration were not verified by the recorded checks. The defect log starts with headers only because no manual defects have been recorded yet.
