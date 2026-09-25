# Production Test Plan

This checklist is for validating the school production fork before deployment.

Primary pilot target:

- `ecjdv.skolamv.cz`

The main school Moodle should only be considered after the ECJDV pilot is stable.

## 1. Pre-flight checks

- Plugin files are deployed from branch `school-production`.
- Moodle reports the expected plugin version.
- Plugin is initially disabled.
- SMTP fallback is disabled.
- Sender mailbox is configured correctly.
- Tenant ID, Client ID and Client Secret are present.
- Exchange Online App RBAC is scoped only to the intended sender mailbox.
- No tenant-wide Graph `Mail.Send` application permission is granted in parallel if App RBAC is used for restriction.
- Core patch status reports `manual_required` before patching.

Expected result: configuration is complete, but no mail is intercepted before the core patch is explicitly applied.

## 2. Core patch workflow

Run:

```bash
php cli/patch.php --status
```

Expected result before first application:

- status = `missing`
- non-zero exit code is acceptable

Then run:

```bash
php cli/patch.php --apply
php cli/patch.php --status
```

Expected result:

- patch applies once
- status = `applied`
- repeated `--apply` reports that the patch is already present
- only one BEGIN and one END marker exist in `lib/phpmailer/moodle_phpmailer.php`

Then verify removal in a maintenance/test copy:

```bash
php cli/patch.php --remove
php cli/patch.php --status
```

Expected result:

- patch is removed cleanly
- core file returns to the unpatched state
- re-apply works again

Do not run the remove test on a live production instance while mail is expected to work.

## 3. OAuth credential test

Use the admin action **Check OAuth credentials**.

Expected result:

- valid credentials obtain an access token
- the UI does not claim that Mail.Send or App RBAC has been verified

Negative tests:

- invalid client secret
- invalid client ID
- invalid tenant ID

Expected result:

- check fails cleanly
- no raw OAuth response body is shown or stored

## 4. Graph sender authorization

Send a real test mail to an external test address.

Expected result:

- HTTP 202
- log status = `Accepted`
- sender is the configured sender mailbox

Negative test:

- temporarily configure a sender mailbox outside the Exchange App RBAC scope

Expected result:

- send fails
- failure is logged
- no SMTP/PHP fallback occurs
- no raw Graph response body is exposed

Restore the approved sender immediately after the test.

## 5. Basic recipient handling

Send one message for each case:

1. TO only
2. TO + CC
3. TO + BCC
4. TO + CC + BCC
5. Multiple TO recipients
6. Multiple CC recipients
7. Multiple BCC recipients

Expected result:

- TO recipients are visible as TO
- CC recipients are visible as CC
- BCC recipients remain hidden from other recipients
- no recipient category is flattened into TO

## 6. Reply-To

Send a message where Moodle/PHPMailer sets Reply-To to a different address from the fixed Graph sender.

Expected result:

- From remains the configured Graph sender mailbox
- Reply-To is preserved
- replying in Outlook/Gmail targets the Reply-To address

## 7. Display names

Test recipient and Reply-To entries with display names.

Expected result:

- Graph message contains both address and display name where supplied
- no encoding corruption occurs with Czech names and diacritics

Suggested names:

- `Příjemce Testovací`
- `Žluťoučký kůň`

## 8. HTML body

Send an HTML message containing:

- headings
- paragraphs
- bold text
- links
- Czech diacritics

Expected result:

- HTML renders correctly
- links work
- UTF-8 text is intact

## 9. Plain-text fallback

Send a message where only AltBody/plain text is available.

Expected result:

- message content remains readable
- line breaks are preserved reasonably
- HTML escaping prevents markup injection

## 10. Standard attachments

Test attachments below the 2 MiB limit:

- PDF
- XLSX
- DOCX
- TXT
- attachment from a temporary file with no extension

Expected result:

- attachment arrives
- filename is correct
- MIME type is usable
- Office attachments open normally

## 11. Oversized attachment

Send an attachment larger than 2 MiB.

Expected result:

- Graph draft/upload-session path is NOT used
- send is rejected with a clear local error
- no `Mail.ReadWrite` permission is required
- failure is logged
- no SMTP fallback occurs

## 12. Inline CID attachment

Send HTML containing an embedded image referenced as:

```html
<img src="cid:testlogo">
```

with an inline attachment having CID `testlogo`.

Expected result:

- image is rendered inline in Outlook
- image is rendered inline in at least one external mail client
- inline attachment is not shown as a normal downloadable attachment unless the client chooses to do so

## 13. Moodle email diverting

Set Moodle email diversion to a controlled test mailbox.

Test:

- normal recipient
- recipient matching a diversion exception
- TO + CC + BCC combination

Expected result:

- non-exempt recipients are redirected
- exempt recipients remain unchanged
- TO/CC/BCC categories remain preserved after diversion
- duplicate diverted addresses are removed within each category

Restore normal diversion settings after the test.

## 14. Token reuse

Trigger several mails in one PHP process, preferably via cron or a scripted Moodle action.

Expected result:

- one OAuth token is reused inside the PHP process
- the plugin does not request a new token for each email
- sending multiple emails remains functional

## 15. Token refresh on 401

In a controlled test, force or simulate an expired/invalid cached token.

Expected result:

- token cache is invalidated
- exactly one new token request is attempted
- the send is retried with the refreshed token
- there is no endless retry loop

## 16. Transient Graph retry

Where practical, simulate or mock:

- HTTP 429
- HTTP 502
- HTTP 503
- HTTP 504

Expected result:

- maximum three total attempts
- `Retry-After` is respected for HTTP 429
- retry delay is bounded
- request does not loop indefinitely

## 17. Network failure behavior

Simulate DNS/network failure or blocked Graph connectivity.

Expected result:

- request stops within configured timeout
- no automatic SMTP fallback occurs
- failure is logged
- the plugin does not blindly repeat ambiguous network timeouts and risk duplicate mail

## 18. Moodle password reset

Request a real password reset for a controlled test account.

Expected result:

- `email_to_user()` succeeds when Graph returns 202
- reset message arrives
- reset link works
- failure is reported to Moodle if Graph cannot accept the message

## 19. Account creation / confirmation

Use a controlled test account flow that generates an account confirmation or new-user email.

Expected result:

- message arrives
- link and Czech text render correctly
- sender and Reply-To are correct

## 20. Course enrolment notification

Trigger a notification related to course enrolment where available.

Expected result:

- message is accepted by Graph
- recipient and body are correct
- no duplicate SMTP mail is generated

## 21. Assignment notification

Trigger a real assignment-related notification.

Suggested cases:

- assignment submission
- grading notification, if enabled

Expected result:

- message is accepted
- content is readable
- no duplicate message is produced

## 22. Forum / cron batch

Trigger several forum or messaging notifications via cron.

Expected result:

- multiple messages can be sent in one cron process
- token reuse works
- no duplicate messages
- no excessive OAuth token requests
- cron finishes within a reasonable time

## 23. SMTP fallback safety

Confirm in plugin settings:

- fallback is OFF

Cause a Graph failure.

Expected result:

- PHP mail / SMTP is not used
- the message fails visibly/logically instead of escaping through the old mail path

Then, only in a controlled test environment, enable fallback temporarily and verify the option behaves as documented.

Production setting must remain OFF.

## 24. Email log

Verify successful and failed attempts.

Expected result:

- success is labelled `Accepted`, not Delivered
- recipient addresses are recorded
- subject is recorded
- attachment flag is correct
- response field contains only sanitised status/error text
- no message body is stored
- no attachment content is stored
- no raw Graph/OAuth response body is stored

## 25. CSV export

Create log records whose text begins with:

- `=1+1`
- `+1`
- `-1`
- `@test`

Export CSV and open it in Excel.

Expected result:

- those cells are treated as text
- no spreadsheet formula is executed

## 26. Log retention

Set retention temporarily to 1 day in a test environment.

Create or alter test records so that some are older than the retention limit.

Run the scheduled task manually.

Expected result:

- expired rows are deleted
- newer rows remain
- task output reports the number of removed rows

Restore retention to 90 days.

## 27. Privacy API sanity check

Verify that Moodle privacy export for a test user can locate mail-log data associated with the user's email address.

Verify deletion behavior.

Expected result:

- matching rows can be exported
- deletion removes matching rows
- limitations of email-string matching are documented and accepted for short-lived operational logs

## 28. Upgrade safety

In a staging/test copy, replace or upgrade Moodle core so the patch disappears.

Expected result:

- plugin reports `manual_required`
- plugin does NOT modify the core file automatically
- mail interception does not silently reactivate
- administrator must review and run `cli/patch.php --apply`

## 29. Uninstall behavior

In a staging/test copy:

- uninstall the plugin while the patch is present

Expected result:

- patch removal succeeds
- no production patch markers remain in the Moodle core file

If removal cannot be completed, the administrator must be clearly informed during operational review.

## 30. Pilot acceptance criteria

The ECJDV pilot can be considered ready only when all of the following are true:

- OAuth credential check passes
- App RBAC sender restriction is verified with both positive and negative tests
- real test mail succeeds
- TO/CC/BCC and Reply-To behave correctly
- normal attachments work
- oversized attachments fail safely
- inline CID image works
- password reset works
- at least one course/activity notification works
- cron/batch mail works
- SMTP fallback is OFF
- failure logging is sanitised
- CSV export is safe
- log retention task works
- core patch can be applied, detected and removed explicitly
- Moodle upgrade does not auto-reapply the patch
- no duplicate mail is observed
