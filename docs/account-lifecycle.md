# Account Lifecycle Recommendations

These features are intentionally deferred. They require an email delivery
decision and should not be approximated with public defaults or development
mailboxes in production.

## Priority 1: Password recovery

This gives the largest immediate user benefit. Add a generic request response
that does not reveal whether an email exists, rate limits by IP and normalized
email, stores only a hash of a random single-use token, expires it after 30
minutes, and revokes all refresh sessions after a successful password change.
Do not put JWTs or account identifiers in reset URLs.

Decisions required before implementation:

- transactional email provider, verified sending domain and sender address;
- synchronous delivery versus a small durable retry queue;
- reset-token lifetime and whether issuing a new token invalidates older ones;
- user-facing behavior for deleted and unverified accounts.

## Priority 2: Email verification

Use the same delivery foundation, hashed single-use tokens and enumeration-safe
responses. Keep login available initially for a personal project, but require a
verified address before trust-sensitive future features. Changing an email must
clear verification and require confirmation of the new address.

Decisions required before implementation:

- which actions, if any, are blocked until verification;
- verification-token lifetime and resend cooldown;
- whether existing accounts are grandfathered or asked to verify;
- bounce handling and the process for correcting a mistyped address.

## Priority 3: Self-service account deletion

Require recent password re-authentication, revoke refresh sessions in the same
transaction, and start from the existing soft-deletion behavior. Do not cascade
delete social content without an explicit product and retention policy.

Decisions required before implementation:

- immediate soft deletion versus a short cancellation window;
- whether recipes and comments are retained under a tombstone, anonymized, or
  removed after the retention period;
- when email and username may be reused;
- audit, legal-retention and backup-erasure expectations;
- treatment of the last active administrator (the API now always refuses that
  deletion until another administrator is active).
