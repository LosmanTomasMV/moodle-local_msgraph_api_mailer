# School Production Fork

This branch contains the school production variant of the MS Graph API Mailer plugin.

## Purpose

The upstream project is used as the technical base, but this fork is intended for controlled deployment in school Moodle environments.

Primary target for the pilot deployment:

- `ecjdv.skolamv.cz`

A later deployment may also be considered for:

- `eskola.skolamv.cz`

## Branch policy

- `main` stays as the clean fork of upstream.
- `school-production` is the working branch for reviewed school-specific changes.
- Functional changes should be made in small, reviewable commits.

## Production requirements identified during audit

Before production deployment, the fork should address at least the following:

1. Keep Microsoft Graph permissions at least privilege: `Application Mail.Send` only.
2. Scope the application to the intended sender mailbox through Exchange Online App RBAC.
3. Remove the large-attachment draft/upload-session path that requires `Mail.ReadWrite`.
4. Preserve TO, CC, BCC and Reply-To semantics.
5. Keep a fixed Graph sender mailbox while preserving Reply-To where Moodle sets it.
6. Disable SMTP/PHP fallback in production.
7. Improve OAuth access-token reuse within a PHP process.
8. Add bounded retry/backoff for transient Graph failures.
9. Add a small retry queue for transient failures only.
10. Replace automatic core re-patching after Moodle upgrades with a controlled/manual workflow.
11. Add safer core-patch markers, validation and rollback handling.
12. Add log retention, initially 90 days.
13. Sanitize stored Graph error details.
14. Protect CSV export against spreadsheet formula injection.

## Moodle core patch policy

This fork currently depends on a modification to:

`lib/phpmailer/moodle_phpmailer.php`

The production variant must not silently re-apply this modification after a Moodle upgrade.

Expected upgrade workflow:

1. Upgrade Moodle.
2. Review the current `moodle_phpmailer.php` implementation.
3. Verify that the patch is still compatible.
4. Apply the patch explicitly.
5. Purge caches if required.
6. Send a test message.
7. Test representative Moodle notification scenarios.

## Deployment principle

The first production-like deployment should be performed on the smaller ECJDV Moodle instance. Only after successful testing and a period of stable operation should the same fork be considered for the main school Moodle instance.

## Security notes

Do not commit tenant secrets, client secrets, access tokens, passwords or other credentials to this repository.
