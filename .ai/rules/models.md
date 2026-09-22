---
paths:
  - 'app/Http/Controllers/ConsultationController.php,app/Models/Consultation.php'
---

# Models

## Consultation lifecycle, locking and signed-note immutability
Consultation status is a string column driven by the state machine in `Consultation::TRANSITIONS` (queued, assigned, in_progress, draft, ready_for_sign_off, signed, finalized). Always move status via `transitionTo()` — it validates the transition and writes a `consultation_status_changed` AuditLog row, which is how the draft -> signed -> finalized trail is proven. Never assign `status` directly.

A signed or finalized note is append-only. `store()` refuses to re-save one; changes go through `amend()`, which calls `applyAmendment()` (one amendment event = one version bump, however many fields it touched). Opening a signed consultation must not claim an editing lock or call `$visit->updateStatus()` — doing so pulls a closed visit back into the queue.

Locking: `LOCK_TIMEOUT_MINUTES = 3`, client heartbeat 60s, autosave 30s. A lock is only "warm" if `locked_by` AND `locked_at` are set and within the timeout — use `hasActiveLock()`/`isLockedByAnother()` rather than reading `locked_at` directly (it can be null while `locked_by` is set). Taking a warm lock from someone else requires the `override_consultation_lock` permission plus a written reason, and is audited as `consultation_lock_overridden`.

Signing requires exactly one diagnosis of type `primary`; `is_primary` is kept in step with `type` for older code that reads the boolean. Diagnosis `type`/`certainty` are optional on input and default to primary-or-secondary (from `is_primary`) and `confirmed`.

Finalizing must keep charging the consultation fee (`Service` code `CONS-GEN` via `Invoice::addCharge`) and creating the structured `Referral` for outcome `refer` — both were once accidentally dropped in a refactor and broke BillingTest/ReferralTest.
